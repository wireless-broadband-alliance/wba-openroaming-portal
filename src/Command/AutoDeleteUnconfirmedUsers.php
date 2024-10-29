<?php

namespace App\Command;

use App\Entity\DeletedUserData;
use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\UserVerificationStatus;
use App\Service\PgpEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:delete_unconfirmed_users',
    description: 'Allocate providers info from User Entity to the UserExternalAuth Entity',
)]

class AutoDeleteUnconfirmedUsers extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    public function deleteUnconfirmedUsers(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->deleteUnconfirmedUsers();
        $output->writeln('Notificações enviadas com sucesso.');

        return Command::SUCCESS;
    }
}
