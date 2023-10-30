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
        $event->setEventMetadata([
            'platform' => 'Live',
        ]);
        $event->setUser($admin);
        $manager->persist($event);

        $event_2 = new Event();
        $event_2->setEventName(AnalyticalEventType::USER_VERIFICATION);
        $event_2->setEventDatetime(new DateTime());
        $event_2->setUser($admin);
        $manager->persist($event_2);


        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $user->setUuid('user' . $i . '@example.com');
            $user->setEmail('user' . $i . '@example.com');
            $user->setPassword($this->userPasswordHashed->hashPassword($user, 'password123')); // Set a default password
            $user->setIsVerified(true);
            $user->setCreatedAt(new DateTime());
            $manager->persist($user);

            $event = new Event();
            $event->setEventDatetime(new DateTime());
            $event->setUser($user);

            if ($i % 2 == 0) {
                $event->setEventName(AnalyticalEventType::USER_CREATION);
                $event->setEventMetadata([
                    'platform' => 'Live',
                ]);
            } else {
                $event->setEventName(AnalyticalEventType::USER_VERIFICATION);
            }

            $manager->persist($event);

            if ($i % 2 == 0) {
                $downloadEvent = new Event();
                $downloadEvent->setEventName(AnalyticalEventType::DOWNLOAD_PROFILE);
                $downloadEvent->setEventDatetime(new DateTime());
                $downloadEvent->setUser($user);

                $platforms = ['Android', 'Windows', 'iOS', 'macOS'];
                $randomPlatform = $platforms[array_rand($platforms)];

                $downloadEvent->setEventMetadata([
                    'type' => $randomPlatform,
                    'platform' => 'Live',
                ]);

                $manager->persist($downloadEvent);
            }
        }

        $manager->flush();
    }
}
