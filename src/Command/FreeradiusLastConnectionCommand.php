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
        $timestampFreeradius = $this->settingRepository->findOneBy(['name' => 'TIME_STAMP_FREERADIUS_CRON']);

        if (!$timestampFreeradius) {
            $output->writeln('<error>' . 'Setting not found' . '</error>');

            return Command::FAILURE;
        }
        $result = $this->freeradiusConnectionService->checkConnection();
        if ($result['success'] === false) {
            $output->writeln('<error>'.$result['message'].'</error>');

            return Command::FAILURE;
        }

        $lastExecutionTime = $this->settingRepository->findOneBy(['name' => 'TIME_STAMP_FREERADIUS_CRON']->getValue());

        $radAcctData = $this->radiusAccountingRepository->findConnectionTime();

        if ($lastExecutionTime === $radAcctData) {
            $output->writeln('<comment>No changes required</comment>');

            return Command::SUCCESS;
        }

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
         * 1 - Check if the connection to the freeradius table exist with the .env DATABASE_FREERADIUS -> make service
         * 2 - Make the query1 to check the radAccount table content -> make the query on the Repo
         * 3 - Check if the query1 content is the same of the previous execution -> php memory
         * 3.1 - Find a way to add the timeStamp of the last query made -> need to save this on the DB new setting will not be displayed on the UI
         * 3.2 - EPOCH - TIMESTAMP -> save in this format valid for linux based
         * 3.3 - Find a way to get the timeStamp and add 1 for the next query
         * 3.4 - Find a way to increase the setting TIME_STAMP_FREERADIUS_CRON when the command finished the execution
         * 4 - Make the logic to update the profile row on the OpenRoaming db UserRadiusProfile
         * "lastConnection" (start/end Connections)
         * 4.1 - Make the query2 on the OpenRoaming db to get update the rows of each profile if need it
         * 4.2 - Search more for a upsert (insert + update) -> this can be better instead of using a foreach with a extra query for the Operoaming DB
         * 5 - Output a response message like: "Execution ignored same data checked"
         * if the content on the step 2 is the same
         * 5.1 - Output a response message like: "Freeradius Connection Times Updated"
         * if the content on the step 2 is diferent
        */

        // Save new data in cache for next execution comparison
        ++$timestampFreeradius;
        $this->saveLastData($radAcctData);
        $this->settingRepository->save($timestampFreeradius, true);

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
