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

        // Check if the last time execution time is newer then the lasted value on the freeradius DB
        if ($timestampFreeradiusCron < $this->radiusAccountingRepository->findLatestConnectionTime()) {
            // TODO REMAKE THIS QUERY TO USE DISTINCT to filter result also needs to be reworked
            // Freeradius MASSIVE Query with the correct $timestampFreeradiusCron increase from the end of the connected execution
            $radAcctData = $this->radiusAccountingRepository->findConnectionTime(
                (int) $timestampFreeradiusCron->getValue()
            );

            dd($radAcctData, $this->radiusAccountingRepository->findLatestConnectionTime());

            $output->writeln('<comment>No changes required</comment>');

            return Command::SUCCESS;
        }

        dd($radAcctData);
        // TODO REVIEW THIS CODE NEEDS TO USE UPSERT TO IMPROVE OPTIMIZATIONS AND RESOURCES EXECUTION OF THE MACHINE
        $userRadProfData = $this->userRadiusProfileRepository->getLastConnectionData();
        $userRadProfIndexed = [];
        foreach ($userRadProfData as $user) {
            $userRadProfIndexed[$user['radius_user']] = &$user;
        }

        foreach ($radAcctData as $radAcct) {
            $username = $radAcct['username'];

            if (isset($userRadProfIndexed[$username])) {
                $userRadProfIndexed[$username]['lastConnectionAt'] = $radAcct['acctStopTime'];
            }
        }

        // TODO FOR THIS COMMAND
        /*
         * done 1 - Check if the connection to the freeradius table exist with the .env DATABASE_FREERADIUS -> make service
         * done 2 - Check if the TIME_STAMP_FREERADIUS_CRON is valid
         * done 3 - Check if the query1 content is the same of the previous execution -> use the $timestampFreeradiusCron execution time
         * done 3.1 - Make the query1 (radAccount) only after the timestamp validation is complete and valid
         * 4 - Make the logic to update the profile row on the OpenRoaming db, UserRadiusProfile table, "lastConnection" column (start/end Connections)
         * 4.1 - Make the query2 on the OpenRoaming db to get update the rows of each profile if need it
         * 4.2 - Find a way to check if the content is the same to ignore the radAcct query
         * 4.3 - Search more for a upsert (insert + update) -> this can be better instead of using a foreach with a extra query for the Operoaming DB
         * 5 - Output a response message like: "Execution ignored same data checked"
         * if the content on the step 2 is the same
         * 5.1 - Output a response message like: "Freeradius Connection Times Updated"
         * if the content on the step 2 is diferent
         * done 6 - Find a way to always get the timeStamp and updated for the next query of the execution
        */

        // Save new data in Db for next execution comparison
        $timestampFreeradiusCron->setValue(time());
        $this->entityManager->persist($timestampFreeradiusCron);
        $this->entityManager->flush();

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
