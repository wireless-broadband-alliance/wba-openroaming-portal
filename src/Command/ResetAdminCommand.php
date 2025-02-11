<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Repository\UserRepository;
use App\Service\EventActions;
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
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $userPasswordHashed,
        private readonly EventActions $eventActions,
        private readonly UserRepository $userRepository,
    ) {
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
        $admin = $this->userRepository->findAdmin();

        if (!$admin instanceof User) {
            $admin = new User();
            $admin->setUuid('admin@example.com');
            $admin->setEmail('admin@example.com');
            $admin->setPassword($this->userPasswordHashed->hashPassword($admin, 'gnimaornepo'));
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setIsVerified(true);
            $admin->setCreatedAt(new DateTime());
            $this->entityManager->persist($admin);

            // Create and set up the UserExternalAuth entity
            $userExternalAuth = new UserExternalAuth();
            $userExternalAuth->setUser($admin);
            $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT->value);
            $userExternalAuth->setProviderId(UserProvider::EMAIL->value);
            $this->entityManager->persist($userExternalAuth);

            // Save the event Action using the service
            $this->eventActions->saveEvent($admin, AnalyticalEventType::ADMIN_CREATION, new DateTime(), []);
            $this->eventActions->saveEvent($admin, AnalyticalEventType::ADMIN_VERIFICATION, new DateTime(), []);
        }

        // Set password
        $admin->setPassword($this->userPasswordHashed->hashPassword($admin, 'gnimaornepo'));

        $this->entityManager->flush();
    }
}
