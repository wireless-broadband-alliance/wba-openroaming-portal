<?php

namespace App\DataFixtures;


use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserFixture extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHashed
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setUuid('admin@example.com');
        $admin->setEmail('admin@example.com');
        $admin->setPassword($this->userPasswordHashed->hashPassword($admin, 'gnimaornepo'));
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);
        $admin->setCreatedAt(new DateTime());
        $manager->persist($admin);

        $event = new Event();
        $event->setEventName(AnalyticalEventType::USER_CREATION);
        $event->setEventDatetime(new DateTime());
        $event->setUser($admin);
        $manager->persist($event);

        $event_2 = new Event();
        $event_2->setEventName(AnalyticalEventType::USER_VERIFICATION);
        $event_2->setEventDatetime(new DateTime());
        $event_2->setUser($admin);
        $manager->persist($event_2);

        $manager->flush();
    }
}
