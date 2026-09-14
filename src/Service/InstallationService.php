<?php

namespace App\Service;

use App\DTO\InstallationProgressDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\DataBaseSetupType;
use App\Enum\DefaultUser;
use App\Enum\InstallationStep;
use App\Enum\InstallationWidgetStepsEnum;
use App\Enum\OperationMode;
use App\Enum\ProcessStatusType;
use App\Enum\SettingName;
use App\Enum\SettingsConfigType;
use App\Exception\EncryptionException;
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
        private CaptchaValidator $captchaValidator,
        private EncryptionService $encryptionService,
    ) {
    }

    /**
     * TRUSTED_PROXIES, TURNSTILE_KEY, TURNSTILE_SECRET and JWT_PASSPHRASE are each
     * checked and persisted independently onto InstallationProgress. A missing or
     * invalid value for one of them no longer blocks the others from being saved.
     *
     * @throws EncryptionException
     */
    public function verifyEnvSettings(): InstallationProgress
    {
        $installationProgress = new InstallationProgress();
        $installationProgress->setInstallationState(ProcessStatusType::IN_PROGRESS);
        $installationProgress->setCreatedAt(new DateTime());
        $installationProgress->setUpdatedAt(new DateTime());

        $databaseUrl = $this->parameterBag->get('app.database_url');
        if ($databaseUrl && $this->databaseConnectionService->testDatabaseConnection($databaseUrl)) {
            $installationProgress->setDbOpenRoaming($this->encryptionService->encrypt($databaseUrl));
        }

        $databaseFreeRadiusUrl = $this->parameterBag->get('app.database_freeradius_url');
        if (
            $databaseFreeRadiusUrl &&
            $this->databaseConnectionService->testDatabaseConnection($databaseFreeRadiusUrl)
        ) {
            $installationProgress->setDbFreeradius($this->encryptionService->encrypt($databaseFreeRadiusUrl));
        }

        $trustedProxies = $this->parameterBag->get('app.trusted_proxies');
        if ($trustedProxies) {
            $trustedProxiesArray = array_map(trim(...), explode(',', $trustedProxies));
            $installationProgress->setTrustedProxies($trustedProxiesArray);
        }

        $turnstileKey = $this->parameterBag->get('app.turnstile_key');
        if ($turnstileKey) {
            $installationProgress->setTurnstileKey($turnstileKey);
        }

        $turnstileSecret = $this->parameterBag->get('app.turnstile_secret');
        if ($turnstileSecret) {
            $captchaValidation = $this->captchaValidator->validateCredentials($turnstileSecret);
            if ($captchaValidation['success']) {
                $installationProgress->setTurnstileSecret($this->encryptionService->encrypt($turnstileSecret));
            }
        }

        $jwtPassphrase = $this->parameterBag->get('app.jwt_passphrase');
        if ($jwtPassphrase) {
            $installationProgress->setJwtPassphrase($this->encryptionService->encrypt($jwtPassphrase));
        }

        $superAdmin = $this->userRepository->findSuperAdmin();
        if ($superAdmin && $superAdmin->getEmail() !== DefaultUser::ADMIN->value) {
            $installationProgress->setEmailAdmin($superAdmin->getEmail());
            $installationProgress->setAdminConfirmed(true);
        }

        $this->getStep($installationProgress);
        $this->entityManager->persist($installationProgress);
        $this->entityManager->flush();

        return $installationProgress;
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
                $installationProgress->getEmailAdmin() &&
                $installationProgress->isAdminConfirmed()
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
        return InstallationStep::DATABASE->value;
    }

    /**
     * @return array<string, bool>
     */
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
        $verificationCode = (string)random_int(100000, 999999);
        $installationProgress->setConfirmCodeAdmin($verificationCode);
        $installationProgress->setUpdatedAt(new DateTime());
        $this->entityManager->persist($installationProgress);
        $this->entityManager->flush();

        $emailTitle = $this->settingRepository->findOneBy(['name' => SettingName::PAGE_TITLE->value])?->getValue();
        $contactEmail = $this->settingRepository->findOneBy(['name' => SettingName::CONTACT_EMAIL->value])?->getValue();
        $customerLogo = $this->settingRepository->findOneBy(['name' => SettingName::CUSTOMER_LOGO->value])?->getValue();
        $footerImageEnabledSetting = $this->settingRepository->findOneBy(
            ['name' => SettingName::FOOTER_IMAGE_ENABLED->value]
        )?->getValue();
        $footerImageSetting = $this->settingRepository->findOneBy(
            ['name' => SettingName::FOOTER_IMAGE->value]
        )?->getValue();

        $projectDir = $this->parameterBag->get('kernel.project_dir');

        // Logo file check
        $logoPath = !empty($customerLogo) ? $projectDir . '/public' . $customerLogo : null;
        $hasLogo = $logoPath && file_exists($logoPath);

        // Footer Banner file check
        $isFooterEnabled = ($footerImageEnabledSetting === OperationMode::ON->value) && !empty($footerImageSetting);
        $footerPath = $isFooterEnabled ? $projectDir . '/public' . $footerImageSetting : null;
        $hasFooter = $isFooterEnabled && $footerPath && file_exists($footerPath);

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
                'footerImageEnabled' => $hasFooter,
            ]);

        if ($hasLogo) {
            $email->embedFromPath($logoPath, 'logo_cid');
        }

        if ($hasFooter) {
            $email->embedFromPath($footerPath, 'footer_cid');
        }

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
            (int)$nrAttempts,
            $limitTime,
            $eventType
        );
        return count($attempts) < $nrAttempts;
    }

    /**
     * @throws EncryptionException
     */
    public function fillDto(
        InstallationProgress $installationProgress
    ): InstallationProgressDTO {
        $dto = new InstallationProgressDTO();
        $dto->installationState = $installationProgress->getInstallationState();

        $dbOpenRoamingPartials = $this->databaseConnectionService->parseDatabaseUrl(
            $this->decryptOrEmpty($installationProgress->getDbOpenRoaming())
        );
        $dto->dbOpenRoamingUserName = $dbOpenRoamingPartials['username'];
        $dto->dbOpenRoamingPassword = $dbOpenRoamingPartials['password'];
        $dto->dbOpenRoamingIp = $dbOpenRoamingPartials['host'];
        $dto->dbOpenRoamingPort = (string)$dbOpenRoamingPartials['port'];

        $dbFreeradiusPartials = $this->databaseConnectionService->parseDatabaseUrl(
            $this->decryptOrEmpty($installationProgress->getDbFreeradius())
        );
        $dto->dbFreeradiusUserName = $dbFreeradiusPartials['username'];
        $dto->dbFreeradiusPassword = $dbFreeradiusPartials['password'];
        $dto->dbFreeradiusIp = $dbFreeradiusPartials['host'];
        $dto->dbFreeradiusPort = (string)$dbFreeradiusPartials['port'];

        $dto->trustedProxies = implode(',', $installationProgress->getTrustedProxies() ?? []);
        $dto->turnstileKey = $installationProgress->getTurnstileKey();
        $dto->turnstileSecret = $this->decryptOrNull($installationProgress->getTurnstileSecret());

        $dto->emailAdmin = $installationProgress->getEmailAdmin();

        $dto->createdAt = $installationProgress->getCreatedAt();
        $dto->updatedAt = $installationProgress->getUpdatedAt();

        return $dto;
    }

    /**
     * @throws EncryptionException
     */
    public function checkDatabaseSettings(InstallationProgress $installationProgress): bool
    {
        if (
            !$this->envValueMatches(
                DataBaseSetupType::DATABASE_URL->value,
                $this->decryptOrNull($installationProgress->getDbOpenRoaming())
            )
        ) {
            return false;
        }
        return $this->envValueMatches(
            DataBaseSetupType::DATABASE_FREERADIUS_URL->value,
            $this->decryptOrNull($installationProgress->getDbFreeradius())
        );
    }

    /**
     * @throws EncryptionException
     */
    public function checkSettingsValues(InstallationProgress $installationProgress): bool
    {
        if (
            !$this->envValueMatches(
                SettingsConfigType::TRUSTED_PROXIES->value,
                implode(',', $installationProgress->getTrustedProxies() ?? [])
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
                $this->decryptOrNull($installationProgress->getTurnstileSecret())
            )
        ) {
            return false;
        }

        $jwtPassphrase = $installationProgress->getJwtPassphrase();
        if (
            $jwtPassphrase !== null &&
            !$this->envValueMatches(
                SettingsConfigType::JWT_PASSPHRASE->value,
                $this->encryptionService->decrypt($jwtPassphrase)
            )
        ) {
            return false;
        }

        return true;
    }

    public function envValueMatches(string $key, ?string $expectedValue): bool
    {
        $envPath = $this->parameterBag->get('kernel.project_dir') . '/.env';

        if (!file_exists($envPath)) {
            return false;
        }

        $envContent = file_get_contents($envPath);

        $expectedValue ??= '';

        $pattern = sprintf('/^%s=("?)(.*?)\1$/m', preg_quote($key, '/'));

        if (preg_match($pattern, (string)$envContent, $matches)) {
            return trim($matches[2]) === trim($expectedValue);
        }

        return false;
    }

    /**
     * @throws EncryptionException
     */
    public function resetToLastInstallation(): void
    {
        $lastCompleted = $this->installationProgressRepository->getLastCompleted();
        if ($lastCompleted instanceof InstallationProgress) {
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $this->decryptOrEmpty($lastCompleted->getDbOpenRoaming()),
                DataBaseSetupType::DATABASE_URL->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $this->decryptOrEmpty($lastCompleted->getDbFreeradius()),
                DataBaseSetupType::DATABASE_FREERADIUS_URL->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                implode(',', $lastCompleted->getTrustedProxies() ?? []),
                SettingsConfigType::TRUSTED_PROXIES->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $lastCompleted->getTurnstileKey() ?? '',
                SettingsConfigType::TURNSTILE_KEY->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $this->decryptOrEmpty($lastCompleted->getTurnstileSecret()),
                SettingsConfigType::TURNSTILE_SECRET->value
            );
            if ($lastCompleted->getJwtPassphrase() !== null) {
                $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    $this->encryptionService->decrypt($lastCompleted->getJwtPassphrase()),
                    SettingsConfigType::JWT_PASSPHRASE->value
                );
            }

            $adminUser = $this->userRepository->findSuperAdmin();
            if ($adminUser instanceof User) {
                $adminUser->setEmail($lastCompleted->getEmailAdmin());
                $this->entityManager->persist($adminUser);
                $this->entityManager->flush();
            }
        }
    }

    /**
     * @throws EncryptionException
     */
    public function commandToDataBase(InstallationProgress $installationProgress): string
    {
        return 'scripts/update-db-env.sh "' .
            $this->decryptOrEmpty($installationProgress->getDbOpenRoaming()) .
            '" "' .
            $this->decryptOrEmpty($installationProgress->getDbFreeradius()) .
            '"';
    }

    /**
     * @throws EncryptionException
     */
    public function commandToSettings(InstallationProgress $installationProgress): string
    {
        $trustedProxies = implode(',', $installationProgress->getTrustedProxies() ?? []);
        $turnstileKey = $installationProgress->getTurnstileKey() ?? '';
        $turnstileSecret = $this->decryptOrEmpty($installationProgress->getTurnstileSecret());

        if ($installationProgress->getJwtPassphrase() !== null) {
            return 'scripts/update-settings-env.sh "' .
                $this->encryptionService->decrypt($installationProgress->getJwtPassphrase()) .
                '" "' .
                $trustedProxies .
                '" "' .
                $turnstileKey .
                '" "' .
                $turnstileSecret .
                '"';
        }

        return 'scripts/update-settings-env.sh "" "' .
            $trustedProxies .
            '" "' .
            $turnstileKey .
            '" "' .
            $turnstileSecret .
            '"';
    }

    /**
     * @throws EncryptionException
     */
    private function decryptOrEmpty(?string $encryptedValue): string
    {
        return $encryptedValue !== null ? $this->encryptionService->decrypt($encryptedValue) : '';
    }

    /**
     * @throws EncryptionException
     */
    private function decryptOrNull(?string $encryptedValue): ?string
    {
        return $encryptedValue !== null ? $this->encryptionService->decrypt($encryptedValue) : null;
    }
}
