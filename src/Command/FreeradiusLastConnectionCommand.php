<?php

namespace App\Command;

use App\Enum\UserRadiusProfileStatus;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\FreeradiusConnectionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

#[AsCommand(
    name: 'backup:freeradiusLastConnection',
    description: 'Backups all lastConnection made on the freeradius database for the OpenRoaming',
)]
class FreeradiusLastConnectionCommand extends Command
{
    private LockFactory $lockFactory;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RadiusAccountingRepository $radiusAccountingRepository,
        private readonly FreeradiusConnectionService $freeradiusConnectionService,
        private readonly UserRadiusProfileRepository $userRadiusProfileRepository,
        private readonly SettingRepository $settingRepository,
    ) {
        parent::__construct();

        // Set up a filesystem lock (uses /tmp by default)
        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function backupFreeradiusLastConnection(OutputInterface $output): int
    {
        // Check if the check if the connection to the freeradius db is valid
        $result = $this->freeradiusConnectionService->checkConnection();
        if ($result['success'] === false) {
            $output->writeln('<error>'.$result['message'].'</error>');

            return Command::FAILURE;
        }

        // Check if the TIME_STAMP_FREERADIUS_CRON is valid
        $timestampFreeradiusCron = $this->settingRepository->findOneBy(['name' => 'TIME_STAMP_FREERADIUS_CRON']);
        if (!$timestampFreeradiusCron) {
            $output->writeln(
                '<error>Setting "TIME_STAMP_FREERADIUS_CRON" not found. Please run the command "reset:timeStampFreeradiusCron" to create it.</error>'
            );

            return Command::FAILURE;
        }
        if ((int)$timestampFreeradiusCron->getValue() < 0 ||
            $timestampFreeradiusCron->getValue() === null ||
            !ctype_digit($timestampFreeradiusCron->getValue())
        ) {
            $output->writeln(
                '<error>Setting "TIME_STAMP_FREERADIUS_CRON" is invalid or empty. Please reset it using "reset:timeStampFreeradiusCron".</error>'
            );

            return Command::FAILURE;
        }

        // Check if the last time execution time is newer then the latest value on the freeradius DB
        if ($timestampFreeradiusCron->getValue() < $this->radiusAccountingRepository->findLatestConnectionTime()) {
            // Freeradius MASSIVE Query with the correct $timestampFreeradiusCron increase from the end of the connected execution
            $radAcct = $this->radiusAccountingRepository->findConnectionTime(
                (int)$timestampFreeradiusCron->getValue()
            );

            // fetch full entities already
            $activeProfiles = $this->userRadiusProfileRepository->findRadiusUserAndConnectionTimes();

            // map entities by radius_user
            $profileMap = [];
            foreach ($activeProfiles as $profileData) {
                $profileMap[$profileData->getRadiusUser()] = $profileData;
            }

            // now loop radAcct
            foreach ($radAcct as $row) {
                $username = $row['username'] ?? null;
                $startTime = $row['acctStartTime'] ?? null;
                $stopTime = $row['acctStopTime'] ?? null;

                if (!$username || !$startTime || !$stopTime) {
                    continue;
                }

                if (!isset($profileMap[$username])) {
                    continue; // no matching profile
                }

                $entity = $profileMap[$username];

                $needsUpdate = false;

                if ($entity->getLastStartConnectionAt() === null ||
                    $startTime > $entity->getLastStartConnectionAt()
                ) {
                    $entity->setLastStartConnectionAt($startTime);
                    $needsUpdate = true;
                }

                if ($entity->getLastStopConnectionAt() === null ||
                    $stopTime > $entity->getLastStopConnectionAt()
                ) {
                    $entity->setLastStopConnectionAt($stopTime);
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $this->entityManager->persist($entity);
                }
            }

            // Makes sure the timestamp is updated after executing the radAcct query
            $timestampFreeradiusCron->setValue(time());
            $this->entityManager->persist($timestampFreeradiusCron);
            $this->entityManager->flush();
        }

        $output->writeln('<info>Freeradius Connection Times Updated</info>');

        return Command::SUCCESS;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->lockFactory->createLock('backup_freeradius_last_connection');

        if (!$lock->acquire()) {
            $output->writeln('<comment>The command is already running in another process.</comment>');

            return Command::SUCCESS;
        }

        try {
            return $this->backupFreeradiusLastConnection($output);
        } catch (Exception $e) {
            $this->entityManager->rollback();
            $output->writeln('<error>An error occurred: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
