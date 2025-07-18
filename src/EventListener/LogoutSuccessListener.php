<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

readonly class LogoutSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private GetSettings $getSettings,
        private EventActions $eventActions,
        private UserRepository $userRepository,
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

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $platformMode = $data['PLATFORM_MODE']['value'] ? PlatformMode::DEMO->value : PlatformMode::LIVE->value;

        if ($user instanceof User) {
            // Defines the Event to the table
            $eventMetadata = [
                'platform' => $platformMode,
                'ip' => $_SERVER['REMOTE_ADDR'],
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
