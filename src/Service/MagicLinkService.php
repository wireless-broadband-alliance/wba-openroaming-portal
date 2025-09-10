<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\SettingName;
use Random\RandomException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTime;

readonly class MagicLinkService
{
    public function __construct(
        private GetSettings $getSettings,
        private UserRepository $userRepository,
        private SettingRepository $settingRepository,
        private EventRepository $eventRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function canSendLink(User $user): ?Event
    {
        $data = $this->getSettings->getSettings();
        $emailTimer = $data[SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value]["value"];
        $limitTime = new DateTime();
        $limitTime->modify('-' . $emailTimer . ' seconds');

        return $this->eventRepository->findLastLinkSent($user, $limitTime);
    }

    /**
     * @throws RandomException
     */
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
        $linkValidity = $this->settingRepository->findOneBy(['name' => SettingName::LINK_VALIDITY->value])->getValue();
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

        return 'Too many requests. Please wait ' . $interval_seconds . ' seconds before requesting a new login link.';
    }
}
