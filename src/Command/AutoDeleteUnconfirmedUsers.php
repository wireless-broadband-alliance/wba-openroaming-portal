<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\User;
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
        $userRepository = $this->entityManager->getRepository(User::class);
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $users = $userRepository->findAll();
        $settingTime = $settingsRepository->findAllIn(['USER_DELETE_TIME']);
        foreach ($users as $user) {
            $timeString = $settingTime[0]->getValue();
            $time = (int)$timeString;
            $limitTime = $user->getCreatedAt();
            $limitTime->modify("+ {$time} hours");
            $realTime = new \DateTime();
            if (!$user->isVerified()) {
                if ($limitTime < $realTime) {
                    $this->entityManager->remove($user);
                }
            }
            $this->entityManager->flush();
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->deleteUnconfirmedUsers();
        $output->writeln('Notificações enviadas com sucesso.');

        return Command::SUCCESS;
    }
}
