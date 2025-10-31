<?php

namespace App\EventListener;

use App\Entity\Setting;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Service\EventActions;
use DateTime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

readonly class LogoutSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private EventActions $eventActions,
        private SettingRepository $settingRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogoutSuccess',
        ];
    }

    public function onLogoutSuccess(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();

        /** @var Setting $platformModeStatus */
        $platformModeStatus = $this->settingRepository->findOneBy([
            'name' => SettingName::PLATFORM_MODE->value
        ]);

        if ($user instanceof User) {
            $eventMetadata = [
                'platform' => $platformModeStatus,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'uuid' => $user->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::LOGOUT_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );
        }
    }
}
