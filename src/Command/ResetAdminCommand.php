<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\DefaultUser;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\UserRepository;
use App\Service\EventActions;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'reset:super-admin',
    description: 'Reset Super Admin Credentials',
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
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Super admin email address')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Super admin password');
    }

    /**
     * @throws RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the --yes option is provided (comes from a controller), then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will reset the super admin credentials' .
                'to its configured state without deleting any data. [y/N]',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $email = $input->getOption('email')
            ?? $_ENV['SUPERADMIN_EMAIL']
            ?? $_SERVER['SUPERADMIN_EMAIL']
            ?? DefaultUser::ADMIN->value;

        $password = $input->getOption('password')
            ?? $_ENV['SUPERADMIN_PASSWORD']
            ?? $_SERVER['SUPERADMIN_PASSWORD']
            ?? null;

        if (empty($password)) {
            $output->writeln(
                '<error>Error:</error> Super Admin password is required. ' .
                'Specify --password option or set SUPERADMIN_PASSWORD in .env file.'
            );
            return Command::FAILURE;
        }

        // Reset admin user credentials
        $this->resetAdminUser($email, $password);

        $output->writeln('<info>Success:</info> The super admin credentials have been reset.');
        $output->writeln(sprintf('Email: <comment>%s</comment>', $email));
        $output->writeln(sprintf('Password: <comment>%s</comment>', $password));

        return Command::SUCCESS;
    }

    /**
     * @throws RandomException
     */
    protected function resetAdminUser(string $email, string $password): void
    {
        $admin = $this->userRepository->findSuperAdmin();

        if (!$admin instanceof User) {
            $admin = new User();
            $admin->setUuid($email);
            $admin->setEmail($email);
            $admin->setPassword($this->userPasswordHashed->hashPassword($admin, $password));
            $admin->setRoles([AdminRoleType::ROLE_SUPER_ADMIN->value]);
            $admin->setPermissions([]);
            $admin->setIsVerified(true);
            $admin->setForgotPasswordRequest(true);
            $admin->setTwoFAcode((string) random_int(100000, 999999));
            $admin->setTwoFAcodeGeneratedAt(new DateTime());
            $admin->setTwoFAcodeIsActive(true);
            $admin->setCreatedAt(new DateTime());
            $this->entityManager->persist($admin);

            // Create and set up the UserExternalAuth entity
            $userExternalAuth = new UserExternalAuth();
            $userExternalAuth->setUser($admin);
            $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT->value);
            $userExternalAuth->setProviderId(UserProvider::EMAIL->value);
            $this->entityManager->persist($userExternalAuth);

            // Save the event Action using the service
            $this->eventActions->saveEvent(
                $admin,
                AnalyticalEventType::SUPER_ADMIN_CREATION->value,
                new DateTime(),
                []
            );
            $this->eventActions->saveEvent(
                $admin,
                AnalyticalEventType::SUPER_ADMIN_VERIFICATION->value,
                new DateTime(),
                []
            );
        }

        // Set password
        $admin->setUuid($email);
        $admin->setEmail($email);
        $admin->setTwoFAtype(UserTwoFactorAuthenticationStatus::DISABLED->value);
        $admin->setForgotPasswordRequest(true);
        $admin->setRoles([AdminRoleType::ROLE_SUPER_ADMIN->value]);
        $admin->setPermissions([]);
        $admin->setPassword($this->userPasswordHashed->hashPassword($admin, $password));

        $this->entityManager->flush();
    }
}
