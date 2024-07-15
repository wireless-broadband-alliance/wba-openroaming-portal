<?php

namespace App\Command;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'reset:admin',
    description: 'Reset Admin Credentials',
)]
class ResetAdminCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHashed
    ) {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:admin')
            ->setDescription('Reset Admin Credentials')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the --yes option is provided (comes from a controller), then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will reset the admin credentials to its default state without deleting any data. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        // Reset admin user credentials
        $this->resetAdminUser();

        $output->writeln('<info>Success:</info> The admin credentials have been reset to its default state.');

        return Command::SUCCESS;
    }

    protected function resetAdminUser(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $admin = $userRepository->findOneBy(['uuid' => 'admin@example.com']);

        if (!$admin) {
            $admin = new User();
            $admin->setUuid('admin@example.com');
            $admin->setEmail('admin@example.com');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setIsVerified(true);
            $admin->setCreatedAt(new DateTime());
            $this->entityManager->persist($admin);

            $event = new Event();
            $event->setEventName(AnalyticalEventType::USER_CREATION);
            $event->setEventDatetime(new DateTime());
            $event->setUser($admin);
            $this->entityManager->persist($event);

            $event_2 = new Event();
            $event_2->setEventName(AnalyticalEventType::USER_VERIFICATION);
            $event_2->setEventDatetime(new DateTime());
            $event_2->setUser($admin);
            $this->entityManager->persist($event_2);
        }

        // Set password
        $admin->setPassword($this->userPasswordHashed->hashPassword($admin, 'gnimaornepo'));

        $this->entityManager->flush();
    }

}
