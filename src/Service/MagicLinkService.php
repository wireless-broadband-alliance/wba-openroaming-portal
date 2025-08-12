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
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class MagicLinkService
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EventRepository $eventRepository,
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EventActions $eventActions,
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
        ?string $ip,
        ?string $userAgent,
    ): void
    {
        $emailTitle = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $contactEmail = $this->settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();
        $supportTeam = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();

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
                'verificationCode' => $user->getTwoFAcode()
            ]);
        $this->mailer->send($email);

        $eventMetaData = [
            'platform' => PlatformMode::LIVE->value,
            'user_agent' => $userAgent ?? 'Unknown',
            'uuid' => $user->getUuid(),
            'ip' => $ip ?? null,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::LOGIN_WITH_UUID_ONLY_LINK->value,
            new DateTime(),
            $eventMetaData
        );
    }
}