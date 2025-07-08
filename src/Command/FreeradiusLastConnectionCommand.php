<?php

namespace App\Command;

use App\RadiusDb\Repository\RadiusAccountingRepository;
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
    ) {
        parent::__construct();

        // Set up a filesystem lock (uses /tmp by default)
        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);
    }

    public function backupFreeradiusLastConnection(): int
    {
        dd($this->freeradiusConnectionService->checkConnection());

        // TODO FOR THIS COMMAND
        /*
         * 1 - Check if the connection to the freeradius table exist with the .env DATABASE_FREERADIUS -> make service
         * 2 - Make the query1 to check the radAccount table content -> make the query on the Repo
         * 3 - Check if the query1 content is the same of the previous execution -> php memory
         * 4 - Make the logic to update the profile row on the OpenRoaming db UserRadiusProfile "lastConnection" (start/end Connections)
         * 4.1 - Make the query2 on the OpenRoaming db to get update the rows of each profile if need it
         * 5 - Output a response message like: "Execution ignored same data checked" if the content on the step 2 is the same
         * 5.1 - Output a response message like: "Freeradius Connection Times Updated" if the content on the step 2 is diferent
        */

        $radacctData = $this->radiusAccountingRepository->findConnectionTime();
        dd('Freeradius last command logic', $radacctData);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create a lock named uniquely for this command
        $lock = $this->lockFactory->createLock('backup_freeradius_last_connection');

        // Try to acquire the lock, if failed means command is already running
        if (!$lock->acquire()) {
            $output->writeln('<comment>The command is already running in another process.</comment>');
            return Command::SUCCESS;
        }

        try {
            $this->backupFreeradiusLastConnection();
        } catch (Exception $e) {
            $this->entityManager->rollback();
            $output->writeln('<error>An error occurred:</error> ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            // Always release the lock (even if exception happens)
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
