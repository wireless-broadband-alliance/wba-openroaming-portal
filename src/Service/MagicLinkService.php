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
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class MagicLinkService
{
    public function __construct(
        private UserRepository $userRepository,
        private SettingRepository $settingRepository,
        private EventRepository $eventRepository,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function canSendLink(User $user): ?Event
    {
        $emailTimer = $this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value]
        )->getValue();
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
        $linkValiditySetting = $this->settingRepository->findOneBy([
            'name' => SettingName::LINK_VALIDITY->value,
        ]);

        if ($linkValiditySetting === null) {
            return false;
        }

        $linkValidity = (int) $linkValiditySetting->getValue();

        $limitTime = new DateTime();
        $limitTime->modify('-' . $linkValidity . ' minutes');

        $codeGeneratedAt = $user->getTwoFAcodeGeneratedAt();

        if (!$codeGeneratedAt instanceof DateTime) {
            return false;
        }

        return $limitTime < $codeGeneratedAt;
    }

    public function timeToResend(string $timeInterval, Event $event): string
    {
        $lastAttemptTime = $event->getEventDatetime();
        $limitTime = $lastAttemptTime;
        /** @var DateTime $limitTime */
        $limitTime->modify('+' . $timeInterval . ' seconds');
        $now = new DateTime();
        $interval = date_diff($now, $limitTime);
        $interval_seconds = $interval->days * 1440;
        $interval_seconds += $interval->h * 60;
        $interval_seconds += $interval->i;
        $interval_seconds += $interval->s;

        return $this->translator->trans(
            'too_many_requests_wait',
            ['%seconds%' => $interval_seconds],
            'MagicLinkService'
        );
    }

    public function linkCanBeUsed(User $user, string $eventType): bool
    {
        $lastEvent = $this->eventRepository->findLatest2FACodeAttemptEvent(
            $user,
            $eventType
        );

        $linkTimeInterval = (int) $this->settingRepository
            ->findOneBy(['name' => SettingName::LINK_VALIDITY->value])
            ->getValue();

        // If no event exists, make $lastAttemptTime far in the past
        $lastAttemptTime = $lastEvent instanceof Event
            ? $lastEvent->getEventDatetime()
            : new DateTime()->modify('-' . ($linkTimeInterval + 1) . ' minutes');

        $now = new DateTime();
        $interval = $now->diff($lastAttemptTime);

        $interval_minutes = ($interval->days * 1440) + ($interval->h * 60) + $interval->i;

        return $interval_minutes > $linkTimeInterval;
    }
}
