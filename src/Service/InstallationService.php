<?php

namespace App\Service;

use App\DTO\InstallationProgressDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\DataBaseSetupType;
use App\Enum\InstallationStep;
use App\Enum\InstallationWidgetStepsEnum;
use App\Enum\ProcessStatusType;
use App\Enum\SettingName;
use App\Enum\SettingsConfigType;
use App\Repository\EventRepository;
use App\Repository\InstallationProgressRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class InstallationService
{
    public function __construct(
        private InstallationProgressRepository $installationProgressRepository,
        private SettingRepository $settingRepository,
        private ParameterBagInterface $parameterBag,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private DatabaseConnectionService $databaseConnectionService,
        private TranslatorInterface $translator,
        private UserRepository $userRepository,
    ) {
    }

    public function lastInstallation(): ?InstallationProgress
    {
        $lastInstallation = $this->installationProgressRepository->getLast();

        if ($lastInstallation instanceof InstallationProgress) {
            if (
                $lastInstallation->getInstallationState() === ProcessStatusType::COMPLETED ||
                $lastInstallation->getInstallationState() === ProcessStatusType::ABORTED
            ) {
                return null;
            }
            return $lastInstallation;
        }
        return $lastInstallation;
    }

    public function getStep(InstallationProgress $installationProgress): string
    {
        if (
            $installationProgress->getDbOpenRoaming() &&
            $installationProgress->getDbFreeradius()
        ) {
            if (
                $installationProgress->getTurnstileKey() &&
                $installationProgress->getTurnstileSecret() &&
                $installationProgress->getTrustedProxies()
            ) {
                if (
                    $installationProgress->getEmailAdmin() &&
                    $installationProgress->getPasswordAdmin() &&
                    $installationProgress->getAdminConfirmation()
                ) {
                    if (
                        $this->checkDatabaseSettings($installationProgress) &&
                        $this->checkSettingsValues($installationProgress)
                    ) {
                        $installationProgress->setInstallationState(ProcessStatusType::COMPLETED);
                        $this->entityManager->persist($installationProgress);
                        $this->entityManager->flush();
                        return InstallationStep::COMPLETED->value;
                    }
                    return InstallationStep::COMMAND->value;
                }
                return InstallationStep::ADMIN->value;
            }
            return InstallationStep::SETTINGS->value;
        }
        return InstallationStep::DATABASE->value;
    }

    public function getStepperStatus(string $step): array
    {
        $status = [
        InstallationWidgetStepsEnum::DATABASE->value => false,
        InstallationWidgetStepsEnum::SETTINGS->value => false,
        InstallationWidgetStepsEnum::ADMIN_CREDENTIALS->value => false,
        InstallationWidgetStepsEnum::SUMMARY->value => false,
        ];

        if ($step === InstallationStep::SETTINGS->value) {
            $status[InstallationWidgetStepsEnum::DATABASE->value] = true;
        }
        if ($step === InstallationStep::ADMIN->value) {
            $status[InstallationWidgetStepsEnum::DATABASE->value] = true;
            $status[InstallationWidgetStepsEnum::SETTINGS->value] = true;
        }
        if ($step === InstallationStep::COMPLETED->value || $step === InstallationStep::COMMAND->value) {
            $status[InstallationWidgetStepsEnum::DATABASE->value] = true;
            $status[InstallationWidgetStepsEnum::SETTINGS->value] = true;
            $status[InstallationWidgetStepsEnum::ADMIN_CREDENTIALS->value] = true;
        }

        return $status;
    }


  /**
   * @throws RandomException
   * @throws TransportExceptionInterface
   */
    public function sendAdminConfirmationCode(InstallationProgress $installationProgress): void
    {
        $verificationCode = random_int(100000, 999999);
        $installationProgress->setConfirmCodeAdmin($verificationCode);
        $installationProgress->setUpdatedAt(new \DateTime());
        $this->entityManager->persist($installationProgress);
        $this->entityManager->flush();

        $emailTitle = $this->settingRepository->findOneBy(['name' => SettingName::PAGE_TITLE->value])->getValue();
        $contactEmail = $this->settingRepository->findOneBy([
        'name' => SettingName::CONTACT_EMAIL->value,
        ])->getValue();
        $customerLogo = $this->settingRepository->findOneBy([
        'name' => SettingName::CUSTOMER_LOGO->value,
        ])->getValue();
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $logoPath = $projectDir . '/public' . $customerLogo;

        $email = new TemplatedEmail()
        ->from(
            new Address(
                $this->parameterBag->get('app.email_address'),
                $this->parameterBag->get('app.sender_name')
            )
        )
        ->to($installationProgress->getEmailAdmin())
        ->subject($this->translator->trans('adminConfirmationEmail', [], 'InstallationService'))
        ->htmlTemplate('email/installation_admin_code.html.twig')
        ->context([
            'uuid' => $installationProgress->getEmailAdmin(),
            'emailTitle' => $emailTitle,
            'contactEmail' => $contactEmail,
            'code' => $verificationCode,
        ])
        ->embedFromPath($logoPath, 'logo_cid');

        $this->mailer->send($email);
    }

    public function sendAdminVerificationCode(User $user): void
    {
        $verificationCode = random_int(100000, 999999);
        $user->setTwoFAcode($verificationCode);
        $user->setTwoFAcodeGeneratedAt(new DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $emailTitle = $this->settingRepository->findOneBy(['name' => SettingName::PAGE_TITLE->value])->getValue();
        $contactEmail = $this->settingRepository->findOneBy([
        'name' => SettingName::CONTACT_EMAIL->value,
        ])->getValue();
        $customerLogo = $this->settingRepository->findOneBy([
        'name' => SettingName::CUSTOMER_LOGO->value,
        ])->getValue();
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $logoPath = $projectDir . '/public' . $customerLogo;

        $email = new TemplatedEmail()
        ->from(
            new Address(
                $this->parameterBag->get('app.email_address'),
                $this->parameterBag->get('app.sender_name')
            )
        )
        ->to($user->getEmail())
        ->subject($this->translator->trans('adminIdentityVerification', [], 'InstallationService'))
        ->htmlTemplate('email/installation_entity_verification.html.twig')
        ->context([
            'uuid' => $user->getUuid(),
            'emailTitle' => $emailTitle,
            'contactEmail' => $contactEmail,
            'code' => $verificationCode,
        ])
        ->embedFromPath($logoPath, 'logo_cid');

        $this->mailer->send($email);
    }

    public function canSendCode(string $eventType, User $user): bool
    {
        $nrAttempts = $this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value]
        )->getValue();
        $timeToResetAttempts = $this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value]
        )->getValue();
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeToResetAttempts . ' minutes');
        $attempts = $this->eventRepository->find2FACodeAttemptEvent(
            $user,
            $nrAttempts,
            $limitTime,
            $eventType
        );
        return count($attempts) < $nrAttempts;
    }

    public function fillDto(
        InstallationProgress $installationProgress
    ): InstallationProgressDTO {
        $dto = new InstallationProgressDTO();
        $dto->installationState = $installationProgress->getInstallationState();

        $dbOpenRoamingPartials = $this->databaseConnectionService->parseDatabaseUrl(
            $installationProgress->getDbOpenRoaming()
        );
        $dto->dbOpenRoamingUserName = $dbOpenRoamingPartials['username'];
        $dto->dbOpenRoamingPassword = $dbOpenRoamingPartials['password'];
        $dto->dbOpenRoamingIp = $dbOpenRoamingPartials['host'];
        $dto->dbOpenRoamingPort = $dbOpenRoamingPartials['port'];

        $dbFreeradiusPartials = $this->databaseConnectionService->parseDatabaseUrl(
            $installationProgress->getDbFreeradius()
        );
        $dto->dbFreeradiusUserName = $dbFreeradiusPartials['username'];
        $dto->dbFreeradiusPassword = $dbFreeradiusPartials['password'];
        $dto->dbFreeradiusIp = $dbFreeradiusPartials['host'];
        $dto->dbFreeradiusPort = $dbFreeradiusPartials['port'];

        $dto->trustedProxies = implode(',', $installationProgress->getTrustedProxies());
        $dto->turnstileKey = $installationProgress->getTurnstileKey();
        $dto->turnstileSecret = $installationProgress->getTurnstileSecret();

        $dto->emailAdmin = $installationProgress->getEmailAdmin();

        $dto->createdAt = $installationProgress->getCreatedAt();
        $dto->updatedAt = $installationProgress->getUpdatedAt();

        return $dto;
    }

    public function checkDatabaseSettings(InstallationProgress $installationProgress): bool
    {
        if (
            !$this->envValueMatches(
                DataBaseSetupType::DATABASE_URL->value,
                $installationProgress->getDbOpenRoaming()
            )
        ) {
            return false;
        }
        if (
            !$this->envValueMatches(
                DataBaseSetupType::DATABASE_FREERADIUS_URL->value,
                $installationProgress->getDbFreeradius()
            )
        ) {
            return false;
        }
        return true;
    }

    public function checkSettingsValues(InstallationProgress $installationProgress): bool
    {
        if (
            !$this->envValueMatches(
                SettingsConfigType::TRUSTED_PROXIES->value,
                implode(',', $installationProgress->getTrustedProxies())
            )
        ) {
            return false;
        }
        if (
            !$this->envValueMatches(
                SettingsConfigType::TURNSTILE_KEY->value,
                $installationProgress->getTurnstileKey()
            )
        ) {
            return false;
        }
        if (
            !$this->envValueMatches(
                SettingsConfigType::TURNSTILE_SECRET->value,
                $installationProgress->getTurnstileSecret()
            )
        ) {
            return false;
        }
        if (
            $installationProgress->getJwtPassphrase() !== null &&
            !$this->envValueMatches(
                SettingsConfigType::JWT_PASSPHRASE->value,
                $installationProgress->getJwtPassphrase()
            )
        ) {
            return false;
        }
        return true;
    }

    public function envValueMatches(string $key, string $expectedValue): bool
    {
        $envPath = $this->parameterBag->get('kernel.project_dir') . '/.env';
        $envContent = file_get_contents($envPath);

        $pattern = sprintf('/^%s="?(.*?)"?$/m', preg_quote($key, '/'));

        if (preg_match($pattern, $envContent, $matches)) {
            return trim($matches[1], "\"' \r\n") === $expectedValue;
        }

        return false;
    }

    public function resetToLastInstallation(): void
    {
        $lastCompleted = $this->installationProgressRepository->getLastCompleted();
        if ($lastCompleted instanceof InstallationProgress) {
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $lastCompleted->getDbOpenRoaming(),
                DataBaseSetupType::DATABASE_URL->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $lastCompleted->getDbFreeradius(),
                DataBaseSetupType::DATABASE_FREERADIUS_URL->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                implode(',', $lastCompleted->getTrustedProxies()),
                SettingsConfigType::TRUSTED_PROXIES->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $lastCompleted->getTurnstileKey(),
                SettingsConfigType::TURNSTILE_KEY->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $lastCompleted->getTurnstileSecret(),
                SettingsConfigType::TURNSTILE_SECRET->value
            );
            if ($lastCompleted->getJwtPassphrase() !== null) {
                  $this->databaseConnectionService->writeDatabaseUrlToEnv(
                      $lastCompleted->getJwtPassphrase(),
                      SettingsConfigType::JWT_PASSPHRASE->value
                  );
            }

            $adminUser = $this->userRepository->findSuperAdmin();
            if ($adminUser instanceof User) {
                $adminUser->setEmail($lastCompleted->getEmailAdmin());
                $adminUser->setPassword($lastCompleted->getPasswordAdmin());
                $this->entityManager->persist($adminUser);
                $this->entityManager->flush();
            }
        }
    }

    public function commandToDataBase(InstallationProgress $installationProgress): string
    {
        return 'scripts/update-db-env.sh "' .
        $installationProgress->getDbOpenRoaming() .
        '" "' .
        $installationProgress->getDbFreeradius() .
        '"';
    }

    public function commandToSettings(InstallationProgress $installationProgress): string
    {
        if ($installationProgress->getJwtPassphrase() !== null) {
            return 'scripts/update-settings-env.sh "' .
            $installationProgress->getJwtPassphrase() .
            '" "' .
            implode(',', $installationProgress->getTrustedProxies()) .
            '" "' .
            $installationProgress->getTurnstileKey() .
            '" "' .
            $installationProgress->getTurnstileSecret() .
            '"';
        }
        return 'scripts/update-settings-env.sh "" "' .
        implode(',', $installationProgress->getTrustedProxies()) .
        '" "' .
        $installationProgress->getTurnstileKey() .
        '" "' .
        $installationProgress->getTurnstileSecret() .
        '"';
    }
}
