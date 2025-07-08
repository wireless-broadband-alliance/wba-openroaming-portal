<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Entity\UserRadiusProfile;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Symfony\Component\String\u;

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
