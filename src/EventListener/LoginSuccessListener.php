<?php

namespace App\EventListener;

use App\Entity\Setting;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Service\EventActions;
use DateTime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

readonly class LoginSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private EventActions $eventActions,
        private RequestStack $requestStack,
        private SettingRepository $settingRepository
    ) {
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
        $request = $event->getRequest();
        $session = $this->requestStack->getSession();

        if ($user instanceof User) {
            /** @var Setting $loginUuidOnlySetting */
            $loginUuidOnlySetting = $this->settingRepository->findOneBy(
                ['name' => SettingName::LOGIN_WITH_UUID_ONLY->value]
            );

            if (
                $loginUuidOnlySetting->getValue() === 'false'
                && $user->isVerified()
            ) {
                $session->set('session_verified', true);
            }

            // Defines the Event to the table
            $eventMetadata = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $user->getUuid(),
                EventMetadataKeysType::PLATFORM->value => $this->settingRepository->findOneBy(
                    ['name' => SettingName::PLATFORM_MODE->value]
                )->getValue()
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );
        }
    }
}
