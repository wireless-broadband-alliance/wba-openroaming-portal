<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTime;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class MagicLinkService
{
    public function __construct(
        private GetSettings $getSettings,
        private UserRepository $userRepository,
        private SettingRepository $settingRepository,
        private EventRepository $eventRepository,
        private MailerInterface $mailer,
        private ParameterBagInterface $parameterBag,
        private EventActions $eventActions,
        private SendSMS $sendSMS,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function canSendLink(User $user): ?Event
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $emailTimer = $data["TWO_FACTOR_AUTH_RESEND_INTERVAL"]["value"];
        $limitTime = new DateTime();
        $limitTime->modify('-' . $emailTimer . ' seconds');

        return $this->eventRepository->findLastLinkSent($user, $limitTime);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendEmail(
        User $user,
    ): void {
        $magicLinkUrl = $this->magicToken($user);
        if ($user->getUserExternalAuths()[0]->getProviderId() === UserProvider::EMAIL->value) {
            $emailTitle = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
            $contactEmail = $this->settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();

            // LOGIN_TRADITIONAL_REQUEST || LOGIN_CODE_RESEND
            $email = new TemplatedEmail()
                ->from(
                    new Address(
                        $this->parameterBag->get('app.email_address'),
                        $this->parameterBag->get('app.sender_name')
                    )
                )
                ->to($user->getEmail())
                ->subject('Magic Link Login')
                ->htmlTemplate('email/user_login_link.html.twig')
                ->context([
                    'uuid' => $user->getEmail(),
                    'emailTitle' => $emailTitle,
                    'supportTeam' => $emailTitle,
                    'contactEmail' => $contactEmail,
                    'magicLink' => $magicLinkUrl,
                ]);
            $this->mailer->send($email);
        } elseif ($user->getUserExternalAuths()[0]->getProviderId() === UserProvider::PHONE_NUMBER->value) {
            $message = "Welcome back to OpenRoaming! Click the link to login: $magicLinkUrl";
            $this->sendSMS->sendSms($user->getPhoneNumber(), $message);
        }
    }

    public function magicToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setTwoFACode($token);
        $user->setTwoFAcodeIsActive(true);
        $user->setTwoFAcodeGeneratedAt(new DateTime());
        $this->userRepository->save($user, true);
        return $this->urlGenerator->generate('app_login_magic_link', [
            'token' => $user->getTwoFAcode(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function linkValidity(User $user): bool
    {
        $linkValidity = $this->settingRepository->findOneBy(['name' => 'LINK_VALIDITY'])->getValue();
        $limitTime = new DateTime();
        $limitTime->modify('-' . $linkValidity . ' minutes');
        return $limitTime < $user->getTwoFAcodeGeneratedAt();
    }

    public function timeToResend(string $timeInterval, Event $event): string
    {
        $lastAttemptTime = $event instanceof Event ?
            $event->getEventDatetime() : $timeInterval;
        $limitTime = $lastAttemptTime;
        /** @var DateTime $limitTime */
        $limitTime->modify('+' . $timeInterval . ' seconds');
        $now = new DateTime();
        $interval = date_diff($now, $limitTime);
        $interval_seconds = $interval->days * 1440;
        $interval_seconds += $interval->h * 60;
        $interval_seconds += $interval->i;
        $interval_seconds += $interval->s;
        return 'Login link Invalid. Please wait ' . $interval_seconds . ' seconds before trying to send again.';
    }
}
