<?php

namespace App\Service;

use App\DTO\CaptchaDTO;
use App\DTO\InstallationProgressDTO;
use App\DTO\SecurityTxtDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\CaptchaProviderEnum;
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
use InvalidArgumentException;
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
        private EncryptionService $encryptionService,
        private HashArgon2idService $hashArgon2idService,
    ) {
    }

    /**
     * TRUSTED_PROXIES, DB URLs, and JWT_PASSPHRASE are checked and persisted
     * independently onto InstallationProgress.
     *
     * @throws EncryptionException
     */
    public function verifyEnvSettings(): InstallationProgress
    {
        $progress = $this->installationProgressRepository->getLast();

        if (!$progress instanceof InstallationProgress) {
            $progress = new InstallationProgress();
            $progress->setInstallationState(ProcessStatusType::IN_PROGRESS);
            $progress->setCreatedAt(new DateTime());
        }

        $progress->setUpdatedAt(new DateTime());

        // Check OpenRoaming Database Connection
        $databaseUrl = $this->parameterBag->get('app.database_url');
        if (
            $databaseUrl &&
            $this->databaseConnectionService->testDatabaseConnection($databaseUrl)
        ) {
            $progress->setDbOpenRoaming($this->encryptionService->encrypt($databaseUrl));
        }

        // Check FreeRADIUS Database Connection
        $databaseFreeRadiusUrl = $this->parameterBag->get('app.database_freeradius_url');
        if (
            $databaseFreeRadiusUrl &&
            $this->databaseConnectionService->testDatabaseConnection($databaseFreeRadiusUrl)
        ) {
            $progress->setDbFreeradius($this->encryptionService->encrypt($databaseFreeRadiusUrl));
        }

        // Trusted Proxies
        $trustedProxies = $this->parameterBag->get('app.trusted_proxies');
        if ($trustedProxies) {
            $progress->setTrustedProxies(array_map(trim(...), explode(',', $trustedProxies)));
        }

        // JWT Passphrase
        $jwtPassphrase = $this->parameterBag->get('app.jwt_passphrase');
        if ($jwtPassphrase) {
            $progress->setJwtPassphrase($this->encryptionService->encrypt($jwtPassphrase));
        }

        // Mark settings as completed if already COMPLETED or if core envs were auto-discovered
        if (
            $progress->getInstallationState() === ProcessStatusType::COMPLETED ||
            ($progress->getTrustedProxies() !== null && $progress->getJwtPassphrase() !== null)
        ) {
            $progress->setIsSettingsCompleted(true);
        }

        // Admin Email Confirmation
        if ($progress->getEmailAdmin() === null) {
            $superAdmin = $this->userRepository->findSuperAdmin();
            if ($superAdmin && $superAdmin->getEmail() !== DefaultUser::ADMIN->value) {
                $progress->setEmailAdmin($superAdmin->getEmail());
                $progress->setAdminConfirmed(true);
            }
        }

        $this->entityManager->persist($progress);
        $this->entityManager->flush();

        return $progress;
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

    /**
     * Read-only calculation of the active installation step.
     * @throws EncryptionException
     */
    public function getStep(InstallationProgress $installationProgress): InstallationStep
    {
        if (
            !$installationProgress->getDbOpenRoaming() ||
            !$installationProgress->getDbFreeradius()
        ) {
            return InstallationStep::DATABASE;
        }

        if (!$installationProgress->isSettingsCompleted()) {
            return InstallationStep::SETTINGS;
        }

        if (!$installationProgress->getCaptchaProvider() instanceof CaptchaProviderEnum) {
            return InstallationStep::CAPTCHA;
        }

        if (!$installationProgress->isSecurityTxtValid()) {
            return InstallationStep::SECURITY_TXT;
        }

        if (
            !$installationProgress->getEmailAdmin() ||
            !$installationProgress->isAdminConfirmed()
        ) {
            return InstallationStep::ADMIN;
        }

        if (
            !$this->checkDatabaseSettings($installationProgress) ||
            !$this->checkSettingsValues($installationProgress)
        ) {
            return InstallationStep::COMMAND;
        }

        return InstallationStep::COMPLETED;
    }

    /**
     * @return array<string, bool>
     */
    public function getStepperStatus(InstallationStep|string $step, ?InstallationProgress $progress = null): array
    {
        if ($progress instanceof InstallationProgress) {
            return [
                InstallationWidgetStepsEnum::DATABASE->value => $progress->getDbOpenRoaming(
                ) !== null || $progress->getDbFreeradius() !== null,
                InstallationWidgetStepsEnum::SETTINGS->value => $progress->getTrustedProxies(
                ) !== null || $progress->getJwtPassphrase() !== null,
                InstallationWidgetStepsEnum::CAPTCHA->value => $progress->getCaptchaProvider(
                ) instanceof \App\Enum\CaptchaProviderEnum,
                InstallationWidgetStepsEnum::SECURITY_TXT->value => $progress->getSecurityContact(
                ) !== null && $progress->getSecurityExpires() instanceof \DateTimeInterface,
                InstallationWidgetStepsEnum::ADMIN_CREDENTIALS->value => $progress->getEmailAdmin(
                ) !== null && $progress->isAdminConfirmed(),
                InstallationWidgetStepsEnum::SUMMARY->value => $progress->getInstallationState(
                ) === ProcessStatusType::COMPLETED,
            ];
        }

        // Fallback sequential logic — each step implies all previous steps are done.
        // Steps are ordered: DATABASE → SETTINGS → CAPTCHA → SECURITY_TXT → ADMIN → COMMAND → COMPLETED
        $stepValue = $step instanceof InstallationStep ? $step : InstallationStep::from($step);

        $databaseDone = false;
        $settingsDone = false;
        $captchaDone = false;
        $securityTxtDone = false;
        $adminDone = false;
        $summaryDone = false;

        match ($stepValue) {
            InstallationStep::SETTINGS => $databaseDone = true,
            InstallationStep::CAPTCHA => [
                $databaseDone = true,
                $settingsDone = true,
            ],
            InstallationStep::SECURITY_TXT => [
                $databaseDone = true,
                $settingsDone = true,
                $captchaDone = true,
            ],
            InstallationStep::ADMIN => [
                $databaseDone = true,
                $settingsDone = true,
                $captchaDone = true,
                $securityTxtDone = true,
            ],
            InstallationStep::COMMAND,
            InstallationStep::COMPLETED => [
                $databaseDone = true,
                $settingsDone = true,
                $captchaDone = true,
                $securityTxtDone = true,
                $adminDone = true,
            ],
            default => null,
        };

        if ($stepValue === InstallationStep::COMPLETED) {
            $summaryDone = true;
        }

        return [
            InstallationWidgetStepsEnum::DATABASE->value => $databaseDone,
            InstallationWidgetStepsEnum::SETTINGS->value => $settingsDone,
            InstallationWidgetStepsEnum::CAPTCHA->value => $captchaDone,
            InstallationWidgetStepsEnum::SECURITY_TXT->value => $securityTxtDone,
            InstallationWidgetStepsEnum::ADMIN_CREDENTIALS->value => $adminDone,
            InstallationWidgetStepsEnum::SUMMARY->value => $summaryDone,
        ];
    }

    /**
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    public function sendAdminConfirmationCode(InstallationProgress $installationProgress): void
    {
        $verificationCode = (string)random_int(100000, 999999);

        // Hash the code before saving to DB
        $hashedCode = $this->hashArgon2idService->hash($verificationCode);
        $installationProgress->setConfirmCodeAdmin($hashedCode);
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

        // Logo file check: $logoPath is only set when the file exists
        $logoPath = null;
        if (!empty($customerLogo)) {
            $candidateLogoPath = $projectDir . '/public' . $customerLogo;
            if (file_exists($candidateLogoPath)) {
                $logoPath = $candidateLogoPath;
            }
        }

        // Footer Banner file check: $footerPath is only set when enabled and the file exists
        $footerPath = null;
        if ($footerImageEnabledSetting === OperationMode::ON->value && !empty($footerImageSetting)) {
            $candidateFooterPath = $projectDir . '/public' . $footerImageSetting;
            if (file_exists($candidateFooterPath)) {
                $footerPath = $candidateFooterPath;
            }
        }
        $hasFooter = $footerPath !== null;

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

        if ($logoPath !== null) {
            $email->embedFromPath($logoPath, 'logo_cid');
        }

        if ($footerPath !== null) {
            $email->embedFromPath($footerPath, 'footer_cid');
        }

        $this->mailer->send($email);
    }

    public function validateAdminConfirmationCode(
        InstallationProgress $installationProgress,
        string $submittedCode
    ): bool {
        $storedHash = $installationProgress->getConfirmCodeAdmin();
        if ($storedHash === null) {
            return false;
        }

        return $this->hashArgon2idService->verifyHash($submittedCode, $storedHash);
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
    public function fillDto(InstallationProgress $installationProgress): InstallationProgressDTO
    {
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

        // CAPTCHA DTO mappings
        $dto->captchaProvider = ($installationProgress->getCaptchaProvider())?->value;
        $dto->turnstileKey = $this->decryptOrNull($installationProgress->getTurnstileKey());
        $dto->turnstileSecret = $this->decryptOrNull($installationProgress->getTurnstileSecret());
        $dto->recaptcha3Key = $this->decryptOrNull($installationProgress->getRecaptcha3Key());
        $dto->recaptcha3Secret = $this->decryptOrNull($installationProgress->getRecaptcha3Secret());

        $dto->emailAdmin = $installationProgress->getEmailAdmin();

        $dto->securityContact = $installationProgress->getSecurityContact();
        $dto->securityExpires = $installationProgress->getSecurityExpires();
        $dto->securityPgpFingerprint = $installationProgress->getSecurityPgpFingerprint();

        $dto->createdAt = $installationProgress->getCreatedAt();
        $dto->updatedAt = $installationProgress->getUpdatedAt();

        return $dto;
    }

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

    public function checkSettingsValues(InstallationProgress $installationProgress): bool
    {
        $trustedProxies = $installationProgress->getTrustedProxies();
        if (
            !empty($trustedProxies) &&
            !$this->envValueMatches(
                SettingsConfigType::TRUSTED_PROXIES->value,
                implode(',', $trustedProxies)
            )
        ) {
            return false;
        }

        $jwtPassphrase = $this->decryptOrNull($installationProgress->getJwtPassphrase());
        if (
            $jwtPassphrase !== null &&
            !$this->envValueMatches(
                SettingsConfigType::JWT_PASSPHRASE->value,
                $jwtPassphrase
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
        $dbOpenRoaming = $this->decryptOrEmpty($installationProgress->getDbOpenRoaming());
        $dbFreeradius = $this->decryptOrEmpty($installationProgress->getDbFreeradius());

        return sprintf(
            "scripts/update-db-env.sh '%s' '%s'",
            str_replace("'", "'\\''", $dbOpenRoaming),
            str_replace("'", "'\\''", $dbFreeradius)
        );
    }

    /**
     * @throws EncryptionException
     */
    public function commandToSettings(InstallationProgress $installationProgress): string
    {
        $trustedProxies = implode(',', $installationProgress->getTrustedProxies() ?? []);
        $jwtPassphrase = $installationProgress->getJwtPassphrase() !== null
            ? $this->encryptionService->decrypt($installationProgress->getJwtPassphrase())
            : '';

        return sprintf(
            "scripts/update-settings-env.sh '%s' '%s'",
            str_replace("'", "'\\''", $jwtPassphrase),
            str_replace("'", "'\\''", $trustedProxies)
        );
    }

    /**
     * @throws EncryptionException
     */
    public function saveCaptchaSettings(CaptchaDTO $dto, InstallationProgress $installationProgress): void
    {
        $installationProgress->setCaptchaProvider($dto->provider);

        if (!empty($dto->turnstileKey)) {
            $encryptedKey = $this->encryptionService->encrypt($dto->turnstileKey);
            $installationProgress->setTurnstileKey($encryptedKey);

            $settingKey = $this->settingRepository->findOneBy(['name' => SettingName::TURNSTILE_KEY->value]);
            if ($settingKey) {
                $settingKey->setValue($encryptedKey);
                $this->entityManager->persist($settingKey);
            }
        }

        if (!empty($dto->turnstileSecret)) {
            $encryptedSecret = $this->encryptionService->encrypt($dto->turnstileSecret);
            $installationProgress->setTurnstileSecret($encryptedSecret);

            $settingSecret = $this->settingRepository->findOneBy(['name' => SettingName::TURNSTILE_SECRET->value]);
            if ($settingSecret) {
                $settingSecret->setValue($encryptedSecret);
                $this->entityManager->persist($settingSecret);
            }
        }

        if (!empty($dto->recaptcha3Key)) {
            $encryptedKey = $this->encryptionService->encrypt($dto->recaptcha3Key);
            $installationProgress->setRecaptcha3Key($encryptedKey);

            $settingKey = $this->settingRepository->findOneBy(['name' => SettingName::RECAPTCHA3_KEY->value]);
            if ($settingKey) {
                $settingKey->setValue($encryptedKey);
                $this->entityManager->persist($settingKey);
            }
        }

        if (!empty($dto->recaptcha3Secret)) {
            $encryptedSecret = $this->encryptionService->encrypt($dto->recaptcha3Secret);
            $installationProgress->setRecaptcha3Secret($encryptedSecret);

            $settingSecret = $this->settingRepository->findOneBy(['name' => SettingName::RECAPTCHA3_SECRET->value]);
            if ($settingSecret) {
                $settingSecret->setValue($encryptedSecret);
                $this->entityManager->persist($settingSecret);
            }
        }

        // Persist provider choice to settings table (store the enum's string value)
        $settingProvider = $this->settingRepository->findOneBy(['name' => SettingName::CAPTCHA_PROVIDER->value]);
        if ($settingProvider) {
            $settingProvider->setValue($dto->provider?->value);
            $this->entityManager->persist($settingProvider);
        }

        $installationProgress->setUpdatedAt(new DateTime());
        $installationProgress->setInstallationState(ProcessStatusType::IN_PROGRESS);

        $this->entityManager->persist($installationProgress);
        $this->entityManager->flush();
    }

    public function saveSecurityTxtSettings(SecurityTxtDTO $dto, InstallationProgress $installationProgress): void
    {
        $expires = $dto->expires ?? throw new InvalidArgumentException('Expires date is required');

        $expiresFormatted = DateTime::createFromInterface($expires)->setTime(23, 59, 59);

        $installationProgress->setSecurityContact($dto->contact);
        $securityContact = $this->settingRepository->findOneBy(['name' => SettingName::SECURITY_CONTACT->value]);
        $securityContact->setValue($dto->contact);
        $this->entityManager->persist($securityContact);

        $installationProgress->setSecurityExpires($expiresFormatted);
        $securityExpires = $this->settingRepository->findOneBy(['name' => SettingName::SECURITY_EXPIRES->value]);
        $securityExpires->setValue($expiresFormatted->format('Y-m-d H:i:s'));
        $this->entityManager->persist($securityExpires);

        $installationProgress->setSecurityPgpFingerprint($dto->pgpFingerprint);
        $pgpFingerprint = $this->settingRepository->findOneBy(['name' => SettingName::SECURITY_PGP_FINGERPRINT->value]);
        $pgpFingerprint->setValue($dto->pgpFingerprint);
        $this->entityManager->persist($pgpFingerprint);

        $installationProgress->setUpdatedAt(new DateTime());
        $installationProgress->setInstallationState(ProcessStatusType::IN_PROGRESS);

        $this->entityManager->persist($installationProgress);
        $this->entityManager->flush();
    }

    /**
     * @throws EncryptionException
     */
    private function decryptOrEmpty(?string $encryptedValue): string
    {
        return $encryptedValue !== null ? $this->encryptionService->decrypt($encryptedValue) : '';
    }

    /**
     * Safely decrypt value or return null on failure or empty input.
     */
    private function decryptOrNull(?string $encryptedValue): ?string
    {
        if ($encryptedValue === null || trim($encryptedValue) === '') {
            return null;
        }

        try {
            return $this->encryptionService->decrypt($encryptedValue);
        } catch (EncryptionException) {
            return null;
        }
    }
}
