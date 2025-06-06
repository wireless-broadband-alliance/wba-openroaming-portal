<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
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
        private SettingRepository $settingRepository,
        private ParameterBagInterface $parameterBag,
        private EventRepository $eventRepository,
        private EventActions $eventActions,
        private TranslatorInterface $translator,
        private UserRepository $userRepository,
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
    ): Email {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');
        $supportTeam = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $contactEmail = $this->settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();

        $user->setTwoFACode(random_int(100000, 999999));
        $user->setTwoFACodeGeneratedAt(new DateTime());
        $user->setTwoFAcodeIsActive(true);
        $this->userRepository->save($user, true);

        $eventMetaData = [
            'platform' => PlatformMode::LIVE->value,
            'user_agent' => $userAgent,
            'uuid' => $user->getUuid(),
            'ip' => $ip,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::SETTING_RESET_CODE_REQUEST->value,
            new DateTime(),
            $eventMetaData
        );

        return new TemplatedEmail()
            ->from(new Address($emailSender, $nameSender))
            ->to($user->getEmail())
            ->subject($this->translator->trans('subject_verify', [], 'admin_reset'))
            ->htmlTemplate('email/admin_reset.html.twig')
            ->context([
                'verificationCode' => $user->getTwoFAcode(),
                'supportTeam' => $supportTeam,
                'contactEmail' => $contactEmail,
            ]);
    }

    /**
     * Create an email message about 2fa disabled by the admin.
     *
     * @return Email The email with the code.
     * @throws Exception
     */
    public function createEmail2FADisabledBy(User $user): Email
    {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        // If the verification code is not provided, generate a new one
        $supportTeam = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $contactEmail = $this->settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();
        $customerLogo = $this->settingRepository->findOneBy(['name' => 'CUSTOMER_LOGO'])->getValue();
        $projectDir =  $this->parameterBag->get('kernel.project_dir');
        $logoPath = $projectDir . '/public' . $customerLogo;

        return new TemplatedEmail()
            ->from(new Address($emailSender, $nameSender))
            ->to($user->getEmail())
            ->subject('Your OpenRoaming Two-Factor Authentication has been disabled')
            ->htmlTemplate('email/admin_disabled_2fa.html.twig')
            ->subject(
                $this->translator->trans(
                    'subject_2fa_disabled',
                    [],
                    'admin_disabled2fa'
                )
            )
            ->context([
                'uuid' => $user->getEmail(),
                'supportTeam' => $supportTeam,
                'contactEmail' => $contactEmail
            ])
            ->embedFromPath($logoPath, 'logo_cid');
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
}
