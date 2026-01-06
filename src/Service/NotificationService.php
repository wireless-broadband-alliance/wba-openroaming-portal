<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

readonly class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function createNotification(User $user, string $eventType): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setLastNotification(new DateTime());
        $notification->setType($eventType);
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        return $notification;
    }
}