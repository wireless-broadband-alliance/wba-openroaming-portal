<?php

namespace App\Command;

use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\FreeradiusConnectionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
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
        private readonly CacheItemPoolInterface $cache, // concrete adapter to get set()
        private readonly UserRadiusProfileRepository $userRadiusProfileRepository,
    ) {
        parent::__construct();

        // Set up a filesystem lock (uses /tmp by default)
        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function backupFreeradiusLastConnection(OutputInterface $output): int
    {
        $result = $this->freeradiusConnectionService->checkConnection();
        if ($result['success'] === false) {
            $output->writeln('<error>'.$result['message'].'</error>');

            return Command::FAILURE;
        }

        $lastData = $this->getLastData(); // Get data from last command execution
        $radAcctData = $this->radiusAccountingRepository->findConnectionTime();
        dd($radAcctData, $lastData);

        if ($lastData === $radAcctData) {
            $output->writeln('<comment>No changes required</comment>');

            return Command::SUCCESS;
        }

        $userRadProfData = $this->userRadiusProfileRepository->getLastConnectionData();
        foreach ($userRadProfData as $userRadProf) {
            // ... update profiles logic
        }

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

        // Save new data in cache for next execution comparison
        $this->saveLastData($radAcctData);

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

    /**
     * @throws InvalidArgumentException
     */
    private function getLastData(): ?array
    {
        $item = $this->cache->getItem('freeradius_last_data');
        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function saveLastData(array $data): void
    {
        $item = $this->cache->getItem('freeradius_last_data');
        $item->set($data);
        $item->expiresAfter(3600); // For security reason, this query will be cleared after this time frame
        $this->cache->save($item);
    }
}
