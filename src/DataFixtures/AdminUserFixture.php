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

        for ($i = 1; $i <= 30; $i++) {
            $user = new User();
            $user->setUuid('user' . $i . '@example.com');
            $user->setEmail('user' . $i . '@example.com');
            $user->setPassword($this->userPasswordHashed->hashPassword($user, '123'));
            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(true);
            $user->setCreatedAt(new \DateTime());
            $manager->persist($user);

            $event = new Event();
            $event->setEventName(AnalyticalEventType::USER_CREATION);
            $event->setEventDatetime(new \DateTime());
            $event->setUser($user);
            $manager->persist($event);

            $event_2 = new Event();
            $event_2->setEventName(AnalyticalEventType::USER_VERIFICATION);
            $event_2->setEventDatetime(new \DateTime());
            $event_2->setUser($user);
            $manager->persist($event_2);
        }

        $manager->flush();
    }
}
