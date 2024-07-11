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
use Symfony\Component\Security\Http\Event\LogoutEvent;


class LogoutSuccessListener implements EventSubscriberInterface
{
    private GetSettings $getSettings;
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private EventActions $eventActions;

    /**
     * @param GetSettings $getSettings
     * @param UserRepository $userRepository
     * @param SettingRepository $settingRepository
     * @param EventActions $eventAction
     */
    public function __construct(
        GetSettings       $getSettings,
        UserRepository    $userRepository,
        SettingRepository $settingRepository,
        EventActions      $eventActions,
    )
    {
        $this->getSettings = $getSettings;
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->eventActions = $eventActions;
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
        if (!$data['PLATFORM_MODE']['value']) {
            $platformMode = PlatformMode::Live;
        } else {
            $platformMode = PlatformMode::Demo;
        }

        if ($user instanceof User) {
            // Defines the Event to the table
            $eventMetadata = [
                'platform' => $platformMode,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'uuid' => $user->getUuid(),
            ];
            $this->eventActions->saveEvent($user, AnalyticalEventType::LOGOUT_REQUEST, new DateTime(), $eventMetadata);
        }

    }
}
