<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'backup:freeradiusLastConnection',
    description: 'Backups all lastConnection made on the freeradius database for the OpenRoaming',
)]
class FreeradiusLastConnectionCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    public function backupFreeradiusLastConnection(): int
    {
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

        dd('Freeradius last connection');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->backupFreeradiusLastConnection();
        } catch (Exception $e) {
            // Handle any exceptions and roll back in case of an error
            $this->entityManager->rollback();
            $output->writeln('<error>An error occurred:</error> ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
