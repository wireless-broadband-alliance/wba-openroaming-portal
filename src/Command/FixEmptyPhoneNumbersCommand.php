<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:fix-empty-phone-numbers',
    description: 'Fix users where phoneNumber is empty string and set it to NULL.',
)]
class FixEmptyPhoneNumbersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $affectedRows = $this->fixEmptyPhoneNumbers();

        $output->writeln("✔ Fixed {$affectedRows} users.");

        return Command::SUCCESS;
    }

    private function fixEmptyPhoneNumbers(): int
    {
        return $this->entityManager
            ->createQuery(
                'UPDATE App\Entity\User u
             SET u.phoneNumber = NULL
             WHERE u.phoneNumber = :empty'
            )
            ->setParameter('empty', '')
            ->execute();
    }
}
