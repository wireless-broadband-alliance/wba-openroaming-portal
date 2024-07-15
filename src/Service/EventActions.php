<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class EventActions
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function saveEvent(User $user, string $eventName, DateTime $dateTime, array $eventMetadata): void
    {
        $event = new Event();
        $event->setUser($user);
        $event->setEventDatetime($dateTime);
        $event->setEventName($eventName);
        $event->setEventMetadata($eventMetadata);

        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }
}
