<?php

namespace App\DataFixtures;


use App\Entity\Events;
use App\Entity\User;
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
        $admin->setUuid('admin');
        $admin->setEmail('admin@example.com');
        $admin->setPassword($this->userPasswordHashed->hashPassword($admin, 'gnimaornepo'));
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);
        $admin->setCreatedAt(new DateTime());
        $manager->persist($admin);

        $event = new Events();
        $event->setEventName("USER_CREATION");
        $event->setEventDatetime(new DateTime());
        $event->setUser($admin);
        $manager->persist($event);

        $event_2 = new Events();
        $event_2->setEventName("USER_VERIFICATION");
        $event_2->setEventDatetime(new DateTime());
        $event_2->setUser($admin);
        $manager->persist($event_2);


        $manager->flush();
    }
}
