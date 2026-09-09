<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\SettingType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use DateTime;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class VerificationCodeEmailGenerator
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private EventRepository $eventRepository,
        private EventActions $eventActions,
        private TranslatorInterface $translator,
        private UserRepository $userRepository,
        private GetSettings $getSettings,
    ) {
    }

    /**
     * Create an email message with the verification code.
     *
     * @return Email The email with the code.
     * @throws Exception
     */
    public function createEmailAdminPage(
        User $user,
        string $ip,
        string $userAgent,
        string $settingCategory
    ): Email {
        $settings = $this->fetchStandardSettings();

        $supportTeam = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $user->setTwoFACode((string) random_int(100000, 999999));
        $user->setTwoFACodeGeneratedAt(new DateTime());
        $user->setTwoFAcodeIsActive(true);
        $this->userRepository->save($user, true);

        // Convert string to enum and translate
        $enum = SettingType::from($settingCategory);
        $translatedCategory = $this->translator->trans($enum->getTranslationKey(), [], 'setting_type');

        $eventMetaData = [
            EventMetadataKeysType::PLATFORM->value => PlatformMode::LIVE->value,
            EventMetadataKeysType::USER_AGENT->value => $userAgent,
            EventMetadataKeysType::UUID->value => $user->getUuid(),
            EventMetadataKeysType::IP->value => $ip,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::SETTING_RESET_CODE_REQUEST->value,
            new DateTime(),
            $eventMetaData
        );

        $context = [
            'verificationCode' => $user->getTwoFAcode(),
            'supportTeam' => $supportTeam,
            'contactEmail' => $contactEmail,
            'settingCategory' => $translatedCategory,
        ];

        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        $email = new TemplatedEmail()
            ->from(new Address($emailSender, $nameSender))
            ->to($user->getEmail())
            ->subject($this->translator->trans('subject_verify', [], 'admin_reset'))
            ->htmlTemplate('email/admin_reset.html.twig');

        $this->configureEmailMedia($email, $settings, $context);

        return $email;
    }

    /**
     * Create an email message about 2fa disabled by the admin.
     *
     * @return Email The email with the code.
     * @throws Exception
     */
    public function createEmail2FADisabledBy(User $user): Email
    {
        $settings = $this->fetchStandardSettings();

        $supportTeam = $this->getVal($settings, SettingName::PAGE_TITLE);
        $contactEmail = $this->getVal($settings, SettingName::CONTACT_EMAIL);

        $context = [
            'uuid' => $user->getEmail(),
            'supportTeam' => $supportTeam,
            'contactEmail' => $contactEmail,
        ];

        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        $email = new TemplatedEmail()
            ->from(new Address($emailSender, $nameSender))
            ->to($user->getEmail())
            ->subject(
                $this->translator->trans(
                    'subject_2fa_disabled',
                    [],
                    'admin_disabled2fa'
                )
            )
            ->htmlTemplate('email/admin_disabled_2fa.html.twig');

        $this->configureEmailMedia($email, $settings, $context);

        return $email;
    }

    public function timeLeftToResendCode(int $timeInterval, ?Event $event): null|int
    {
        if ($event instanceof Event) {
            $attemptTime = $event->getEventDatetime();
            if ($attemptTime instanceof \DateTimeInterface) {
                $now = new DateTime();

                // Check and cast to DateTime for modify() method
                if ($attemptTime instanceof DateTime) {
                    $attemptTime->modify('+' . $timeInterval . ' seconds');
                } elseif ($attemptTime instanceof \DateTimeImmutable) {
                    $attemptTime = $attemptTime->modify('+' . $timeInterval . ' seconds');
                }

                $interval = date_diff($now, $attemptTime);
                $interval_seconds = $interval->days * 1440;
                $interval_seconds += $interval->h * 60;
                $interval_seconds += $interval->i;
                return $interval_seconds + $interval->s;
            }
            return null;
        }
        return null;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function canResendCode(User $user, int $timeInterval): bool
    {
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeInterval . ' seconds');
        $attempts = $this->eventRepository->find2FACodeAttemptEvent(
            $user,
            1,
            $limitTime,
            AnalyticalEventType::SETTING_RESET_CODE_REQUEST->value
        );
        return count($attempts) < 1;
    }

    /**
     * Helper to fetch standard settings used by emails in a single query.
     *
     * @return array<string, array{value: string}>
     */
    private function fetchStandardSettings(): array
    {
        return $this->fetchSettings();
    }

    /**
     * Helper to invoke GetSettings::getSpecificSettings with an array of SettingName enums.
     *
     * @return array<string, array{value: string}>
     */
    private function fetchSettings(): array
    {
        $settingNames = [
            SettingName::PAGE_TITLE,
            SettingName::CONTACT_EMAIL,
            SettingName::CUSTOMER_LOGO,
            SettingName::FOOTER_IMAGE_ENABLED,
            SettingName::FOOTER_IMAGE,
        ];
        $keys = array_map(static fn(SettingName $name) => $name->value, $settingNames);

        return $this->getSettings->getSpecificSettings($keys);
    }

    /**
     * Helper to safely get a setting's value.
     *
     * @param array<string, array{value: string}> $settings
     */
    private function getVal(array $settings, SettingName $name): ?string
    {
        return $settings[$name->value]['value'] ?? null;
    }

    /**
     * Helper to embed customer logo and footer banner images,
     * adding 'footerImageEnabled' flag to the context.
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
