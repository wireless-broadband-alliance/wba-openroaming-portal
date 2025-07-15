<?php

namespace App\Command;

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
    private readonly LockFactory $lockFactory;

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
        $output->writeln('<comment>Starting Freeradius backup at ' . date('Y-m-d H:i:s') . '</comment>');

        // Check FreeRadius DB connection
        $result = $this->freeradiusConnectionService->checkConnection();
        if ($result['success'] === false) {
            $output->writeln('<error>' . $result['message'] . '</error>');

            return Command::FAILURE;
        }

        // Load timestamp setting
        $timestampFreeradiusCron = $this->settingRepository->findOneBy(['name' => 'TIME_STAMP_FREERADIUS_CRON']);
        if (!$timestampFreeradiusCron) {
            $output->writeln(
                '<error>Setting "TIME_STAMP_FREERADIUS_CRON" not found. 
                        Please run the command "reset:timeStampFreeradiusCron" to create it.</error>'
            );

            return Command::FAILURE;
        }
        if (
            (int)$timestampFreeradiusCron->getValue() < 0 || $timestampFreeradiusCron->getValue(
            ) === null || !ctype_digit($timestampFreeradiusCron->getValue())
        ) {
            $output->writeln(
                '<error>Setting "TIME_STAMP_FREERADIUS_CRON" is invalid or empty. 
                          Please reset it using "reset:timeStampFreeradiusCron".</error>'
            );

            return Command::FAILURE;
        }

        $updated = false;

        $latestConnectionTime = $this->radiusAccountingRepository->findLatestConnectionTime();

        if ((int)$timestampFreeradiusCron->getValue() < (int)$latestConnectionTime) {
            $this->entityManager->beginTransaction();

            try {
                // Fetch new connection records since last timestamp
                $radAcct = $this->radiusAccountingRepository->findConnectionTime(
                    (int)$timestampFreeradiusCron->getValue()
                );
                $activeProfiles = $this->userRadiusProfileRepository->findRadiusUserAndConnectionTimes();

                $profileMap = [];
                foreach ($activeProfiles as $profileData) {
                    $profileMap[$profileData->getRadiusUser()] = $profileData;
                }

                foreach ($radAcct as $row) {
                    $username = $row['username'] ?? null;
                    $startTime = $row['acctStartTime'] ?? null;
                    $stopTime = $row['acctStopTime'] ?? null;

                    if (!$username || !$startTime || !$stopTime) {
                        continue;
                    }
                    if (!isset($profileMap[$username])) {
                        continue;
                    }

                    $entity = $profileMap[$username];
                    $needsUpdate = false;

                    if (
                        $entity->getLastStartConnectionAt() === null || $startTime > $entity->getLastStartConnectionAt(
                        )
                    ) {
                        $entity->setLastStartConnectionAt($startTime);
                        $needsUpdate = true;
                    }
                    if (
                        $entity->getLastStopConnectionAt() === null || $stopTime > $entity->getLastStopConnectionAt()
                    ) {
                        $entity->setLastStopConnectionAt($stopTime);
                        $needsUpdate = true;
                    }

                    if ($needsUpdate) {
                        $this->entityManager->persist($entity);
                        $updated = true;
                    }
                }

                if ($updated) {
                    // Update timestamp only if changes were made
                    // Make all the db OpenRoaming changes at once to avoid server load
                    $timestampFreeradiusCron->setValue(time());
                    $this->entityManager->persist($timestampFreeradiusCron);
                    $this->entityManager->flush();
                    $this->entityManager->commit();

                    $output->writeln('<info>Freeradius Connection Times Updated</info>');

                    // Optionally clear EM to free memory (if large dataset)
                    $this->entityManager->clear();
                } else {
                    // Rollback transaction if nothing updated
                    $this->entityManager->rollback();

                    $output->writeln('<comment>No changes detected, timestamp not updated.</comment>');
                }
            } catch (Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }
        } else {
            $output->writeln('<comment>No new connections found. Nothing to update.</comment>');
        }

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
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
