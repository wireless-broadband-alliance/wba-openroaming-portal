<?php

namespace App\EventListener;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;


class LoginSuccessListener implements EventSubscriberInterface
{
    private GetSettings $getSettings;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;

    /**
     * @param GetSettings $getSettings
     * @param EntityManagerInterface $entityManager
     * @param UserRepository $userRepository
     * @param SettingRepository $settingRepository
     */
    public function __construct(
        GetSettings            $getSettings,
        EntityManagerInterface $entityManager,
        UserRepository         $userRepository,
        SettingRepository      $settingRepository
    )
    {
        $this->entityManager = $entityManager;
        $this->getSettings = $getSettings;
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        if (!$data['PLATFORM_MODE']['value']) {
            $platformMode = PlatformMode::Live;
        } else {
            $platformMode = PlatformMode::Demo;
        }

        if ($user instanceof User) {
            $eventEntity = new Event();
            $eventEntity->setUser($user);
            $eventEntity->setEventDatetime(new DateTime());
            $eventEntity->setEventName(AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST);
            $eventEntity->setEventMetadata([
                'platform' => $platformMode,
                'isIP' => $_SERVER['REMOTE_ADDR'],
                'uuid' => $user->getUuid(),
            ]);

            $this->entityManager->persist($eventEntity);
            $this->entityManager->flush();
        }
    }
}
