<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class EmailGenerator
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private MailerInterface $mailer,
        private MagicLinkService $magicLinkService,
        private TranslatorInterface $translator,
        private GetSettings $getSettings,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     */
    public function sendRegistrationEmail(
        User $user,
        ?string $password = null,
        bool $returnAppsRegistration = false,
        ?string $rawTwoFaCode = null
    ): void {
        $settings = $this->fetchSettings([
            SettingName::PAGE_TITLE,
            SettingName::CONTACT_EMAIL,
            SettingName::LOGIN_WITH_UUID_ONLY,
            SettingName::RETURN_APPS_ENABLED,
            SettingName::CUSTOMER_LOGO,
            SettingName::FOOTER_IMAGE_ENABLED,
            SettingName::FOOTER_IMAGE,
        ]);

        $supportTeam = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);
        $loginWithUUID = $this->getVal($settings, SettingName::LOGIN_WITH_UUID_ONLY);
        $returnAppsEnabled = $this->getVal($settings, SettingName::RETURN_APPS_ENABLED);

        // Default template and translation domain
        $template = 'email/user_registration.html.twig';
        $translationDomain = 'user_registration';

        $context = [
            'uuid' => $user->getEmail(),
            'supportTeam' => $supportTeam,
            'contactEmail' => $contactEmail,
            'twoFaCode' => $rawTwoFaCode ?? $user->getTwoFAcode(),
            'password' => $password ?? null,
        ];

        // Switch template depending on login mode or return apps setting
        if ($loginWithUUID === 'true') {
            $template = 'email/user_registration_login_uuid.html.twig';
            $translationDomain = 'user_registration_login_uuid';

            $context['magicURL'] = $this->magicLinkService->magicToken($user);
        } elseif ($returnAppsEnabled === OperationMode::ON->value && $returnAppsRegistration) {
            $template = 'email/user_registration_api.html.twig';
            $translationDomain = 'user_registration_api';
        }

        $email = $this->createBaseEmail($user->getEmail())
            ->subject(
                $this->translator->trans(
                    'subject_registration_details',
                    [],
                    $translationDomain
                )
            )
            ->htmlTemplate($template);

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendNotifyExpiresProfileEmail(User $user, int $timeLeft): void
    {
        $settings = $this->fetchStandardSettings();

        $emailTitle = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'uuid' => $user->getEmail(),
            'emailTitle' => $emailTitle,
            'contactEmail' => $contactEmail,
            'timeLeft' => $timeLeft,
        ];

        $email = $this->createBaseEmail($user->getEmail())
            ->subject($this->translator->trans('subject_is_expiring', [], 'expirationProfiles'))
            ->htmlTemplate('email/expiresProfile.html.twig');

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendNotifyExpiredProfile(User $user): void
    {
        $settings = $this->fetchStandardSettings();

        $supportTeam = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'uuid' => $user->getEmail(),
            'contactEmail' => $contactEmail,
            'emailTitle' => $supportTeam,
            'supportTeam' => $supportTeam,
        ];

        $email = $this->createBaseEmail($user->getEmail())
            ->subject($this->translator->trans('subject_is_expired', [], 'expirationProfiles'))
            ->htmlTemplate('email/expiredProfile.html.twig');

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendForgotPasswordEmail(User $user, ?string $rawTwoFaCode = null): void
    {
        $settings = $this->fetchStandardSettings();

        $emailTitle = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'forgotPasswordUser' => true,
            'uuid' => $user->getUuid(),
            'emailTitle' => $emailTitle,
            'contactEmail' => $contactEmail,
            'verificationCode' => $rawTwoFaCode ?? $user->getTwoFAcode(),
            'context' => FirewallType::LANDING->value,
        ];

        $email = $this->createBaseEmail($user->getEmail())
            ->subject(
                $this->translator->trans(
                    'subject_forgot_password',
                    [],
                    'user_forgot_password_request'
                )
            )
            ->htmlTemplate('email/user_forgot_password_request.html.twig');

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendResetPasswordEmailByAdmin(User $user, string $newPassword): void
    {
        $settings = $this->fetchStandardSettings();

        $supportTeam = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'password' => $newPassword,
            'supportTeam' => $supportTeam,
            'contactEmail' => $contactEmail,
        ];

        $email = $this->createBaseEmail($user->getEmail())
            ->subject($this->translator->trans('subject_password_reset_details', [], 'user_password_reset'))
            ->htmlTemplate('email/user_password_reset.html.twig');

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendNotifyExpiresCertEmail(User $user, int $timeLeft): void
    {
        $settings = $this->fetchStandardSettings();

        $emailTitle = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'uuid' => $user->getEmail(),
            'emailTitle' => $emailTitle,
            'contactEmail' => $contactEmail,
            'timeLeft' => $timeLeft,
        ];

        $email = $this->createBaseEmail($user->getEmail())
            ->subject($this->translator->trans('subjectExpiring', [], 'notify_admin_expiring'))
            ->htmlTemplate('email/notify_admin_expiring.html.twig');

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendNotifyExpiredCertEmail(User $user): void
    {
        $settings = $this->fetchStandardSettings();

        $emailTitle = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'uuid' => $user->getEmail(),
            'emailTitle' => $emailTitle,
            'contactEmail' => $contactEmail,
        ];

        $email = $this->createBaseEmail($user->getEmail())
            ->subject($this->translator->trans('subjectExpired', [], 'notify_admin_expiring'))
            ->htmlTemplate('email/notify_admin_expired.html.twig');

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendUserAccountDeletionConfirmationEmail(User $user): void
    {
        $settings = $this->fetchStandardSettings();

        $supportTeam = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'uuid' => $user->getEmail(),
            'supportTeam' => $supportTeam,
            'contactEmail' => $contactEmail,
        ];

        $email = $this->createBaseEmail($user->getEmail())
            ->subject(
                $this->translator->trans(
                    'subject_account_deletion_confirmation',
                    [],
                    'account_deletion_confirmation_by_user'
                )
            )
            ->htmlTemplate('email/account_deletion_confirmation_by_user.html.twig');

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendAdminUserDeletionAccountConfirmationEmail(
        User $deletedUser,
        User $adminRecipient,
        User $performedBy
    ): void {
        $settings = $this->fetchStandardSettings();

        $supportTeam = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'uuid' => $deletedUser->getUuid(),
            'adminName' => trim($performedBy->getFirstName() . ' ' . $performedBy->getLastName()),
            'adminEmail' => $performedBy->getEmail(),
            'supportTeam' => $supportTeam,
            'contactEmail' => $contactEmail,
        ];

        $email = $this->createBaseEmail($adminRecipient->getEmail())
            ->subject(
                $this->translator->trans(
                    'subject_admin_account_deletion_confirmation',
                    [],
                    'account_deletion_confirmation_by_admins'
                )
            )
            ->htmlTemplate('email/account_deletion_confirmation_by_admins.html.twig');

        $this->configureEmailMedia($email, $settings, $context);
        $this->mailer->send($email);
    }

    /**
     * Helper to instantiate a TemplatedEmail with pre-configured 'from' and 'to' addresses.
     */
    private function createBaseEmail(string $recipientEmail): TemplatedEmail
    {
        return new TemplatedEmail()
            ->from(
                new Address(
                    $this->parameterBag->get('app.email_address'),
                    $this->parameterBag->get('app.sender_name')
                )
            )
            ->to($recipientEmail);
    }

    /**
     * Helper to fetch common standard settings used by most emails.
     *
     * @return array<string, array{value: string}>
     */
    private function fetchStandardSettings(): array
    {
        return $this->fetchSettings([
            SettingName::PAGE_TITLE,
            SettingName::CONTACT_EMAIL,
            SettingName::CUSTOMER_LOGO,
            SettingName::FOOTER_IMAGE_ENABLED,
            SettingName::FOOTER_IMAGE,
        ]);
    }

    /**
     * Helper to invoke GetSettings::getSpecificSettings with an array of SettingName enums.
     *
     * @param SettingName[] $settingNames
     * @return array<string, array{value: string}>
     */
    private function fetchSettings(array $settingNames): array
    {
        $keys = array_map(static fn(SettingName $name) => $name->value, $settingNames);

        return $this->getSettings->getSpecificSettings($keys);
    }

    /**
     * Helper to safely get a setting's value from the array returned by GetSettings.
     *
     * @param array<string, array{value: string}> $settings
     */
    private function getVal(array $settings, SettingName $name): ?string
    {
        return $settings[$name->value]['value'] ?? null;
    }

    /**
     * Helper to embed customer logo and footer banner images into the email,
     * updating the context array with 'footerImageEnabled' flag before applying it to the email.
     *
     * @param array<string, array{value: string}> $settings
     * @param array<string, mixed> $context
     */
    private function configureEmailMedia(
        TemplatedEmail $email,
        array $settings,
        array $context
    ): void {
        $projectDir = $this->parameterBag->get('kernel.project_dir');

        $customerLogo = $this->getVal($settings, SettingName::CUSTOMER_LOGO);
        $footerImageEnabled = $this->getVal($settings, SettingName::FOOTER_IMAGE_ENABLED);
        $footerImage = $this->getVal($settings, SettingName::FOOTER_IMAGE);

        // Embed Customer Logo
        if (!empty($customerLogo)) {
            $logoPath = $projectDir . '/public' . $customerLogo;
            if (file_exists($logoPath)) {
                $email->embedFromPath($logoPath, 'logo_cid');
            }
        }

        // Embed Footer Banner
        $isFooterEnabled = ($footerImageEnabled === OperationMode::ON->value) && !empty($footerImage);
        $footerPath = $isFooterEnabled ? $projectDir . '/public' . $footerImage : null;
        $hasFooter = $isFooterEnabled && $footerPath && file_exists($footerPath);

        $context['footerImageEnabled'] = $hasFooter;

        if ($hasFooter) {
            $email->embedFromPath($footerPath, 'footer_cid');
        }

        $email->context($context);
    }
}
