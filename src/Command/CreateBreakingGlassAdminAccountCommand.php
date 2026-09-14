<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AdminPermissionsType;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Exception\EncryptionException;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EncryptionService;
use App\Service\TwoFAService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'backup:createBreakingGlassAdmin',
    description: 'Creates a temporary emergency administrator account.'
)]
class CreateBreakingGlassAdminAccountCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly TwoFAService $twoFAService,
        private readonly EncryptionService $encryptionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Automatically confirm the creation of a new administrator account.'
            );
    }

    /**
     * @throws RandomException
     * @throws EncryptionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will create or reactivate the break-glass administrator account. ' .
                'Do you want to continue? [y/N] ',
                false
            );
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $setting = $this->settingRepository->findOneBy(['name' => SettingName::BREAKING_GLASS_ADMIN_EMAIL->value]);

        if (!$setting) {
            $output->writeln(
                '<error>Setting BREAKING_GLASS_ADMIN_EMAIL not found. Ensure the database is seeded.</error>'
            );
            return Command::FAILURE;
        }

        $plainPassword = bin2hex(random_bytes(16));
        $allPermissions = array_map(
            static fn(AdminPermissionsType $p) => $p->value,
            AdminPermissionsType::cases()
        );

        $rawValue = $setting->getValue();
        $existingEmail = (!empty($rawValue)) ? $this->encryptionService->decrypt($rawValue) : null;
        $existingUser = $existingEmail
            ? $this->userRepository->findOneBy(['email' => $existingEmail])
            : null;
        $isValidBreakGlassEmail = $existingEmail !== null
            && str_starts_with($existingEmail, 'breakglass_');

        if ($existingUser instanceof User && $isValidBreakGlassEmail) {
            $actingUser = $this->reactivateAccount($existingUser, $plainPassword, $allPermissions);
            $email = $actingUser->getEmail();
        } else {
            [$actingUser, $email] = $this->createAccount($setting, $plainPassword, $allPermissions);
        }

        $this->logEvent($actingUser);

        $this->entityManager->flush();

        $this->twoFAService->generateOTPCodes($actingUser);

        $this->renderOutput($output, $email, $plainPassword);

        return Command::SUCCESS;
    }

    /**
     * @param string[] $allPermissions
     */
    private function reactivateAccount(User $user, string $plainPassword, array $allPermissions): User
    {
        $user->setUuid($user->getEmail());
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setDeletedAt(null);
        $user->setDisabled(false);
        $user->setIsVerified(true);
        $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::BYPASS->value);
        $user->setRoles([AdminRoleType::ROLE_ADMIN->value]);
        $user->setPermissions($allPermissions);
        $user->setFirstName('Breaking');
        $user->setLastName('Glass');

        $this->ensureExternalAuth($user);

        $this->entityManager->persist($user);

        return $user;
    }

    /**
     * @param string[] $allPermissions
     * @return array{0: User, 1: string}
     * @throws RandomException
     * @throws EncryptionException
     */
    private function createAccount(mixed $setting, string $plainPassword, array $allPermissions): array
    {
        $username = sprintf('breakglass_%s', bin2hex(random_bytes(4)));
        $email = $username . '@openroaming.com';

        $setting->setValue($this->encryptionService->encrypt($email));

        $user = new User();
        $user->setUuid($email);
        $user->setEmail($email);
        $user->setCreatedAt(new DateTime());
        $user->setIsVerified(true);
        $user->setDisabled(false);
        $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::BYPASS->value);
        $user->setRoles([AdminRoleType::ROLE_ADMIN->value]);
        $user->setPermissions($allPermissions);
        $user->setFirstName('Breaking');
        $user->setLastName('Glass');
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->ensureExternalAuth($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($setting);

        return [$user, $email];
    }

    private function logEvent(User $actingUser): void
    {
        $hostname = gethostname();
        $ip = $hostname ? gethostbyname($hostname) : '';

        $event = new Event();
        $event->setUser($actingUser);
        $event->setEventDatetime(new DateTime());
        $event->setEventName(AnalyticalEventType::BREAKING_GLASS_ACCOUNT_GENERATION->value);
        $event->setEventMetadata([
            EventMetadataKeysType::PLATFORM->value => PlatformMode::CLI->value,
            EventMetadataKeysType::IP->value => $ip,
        ]);

        $this->entityManager->persist($event);
    }

    private function ensureExternalAuth(User $user): void
    {
        // Avoid duplicating if already exists (reactivation case)
        foreach ($user->getUserExternalAuths() as $existing) {
            if (
                $existing->getProvider() === UserProvider::PORTAL_ACCOUNT->value
                && $existing->getProviderId() === UserProvider::EMAIL->value
            ) {
                return;
            }
        }

        $externalAuth = new UserExternalAuth();
        $externalAuth->setProvider(UserProvider::PORTAL_ACCOUNT->value);
        $externalAuth->setProviderId(UserProvider::EMAIL->value);
        $externalAuth->setUser($user);

        $this->entityManager->persist($externalAuth);
    }

    private function renderOutput(OutputInterface $output, string $email, string $plainPassword): void
    {
        $output->writeln('');
        $output->writeln('<info>Break-glass admin created successfully.</info>');
        $output->writeln('');

        $table = new Table($output);
        $table
            ->setHeaders(['Field', 'Value'])
            ->setRows([
                ['Email', $email],
                ['Password', $plainPassword],
            ]);
        $table->render();

        $output->writeln('');
        $output->writeln('<comment>Store these credentials securely. This password will not be shown again.</comment>');
    }
}
