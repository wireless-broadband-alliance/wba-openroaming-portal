<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Service\EventActions;
use DateTime;
use DateTimeInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

readonly class LogoutSuccessListener
{
    public function __construct(
        private EventActions $eventActions,
    ) {
    }
    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogoutSuccess(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        $request = $event->getRequest();

        if ($user instanceof User) {
            $isDeleted = $user->getDeletedAt() instanceof DateTimeInterface;

            $eventMetadata = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $isDeleted ? null : $user->getUuid(),
                EventMetadataKeysType::ID->value => $user->getId(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::LOGOUT_REQUEST->value,
                new DateTime(),
                $eventMetadata,
                $isDeleted  // <-- pass containsEncryptedData = true when deleted
            );
        }
    }
}
