<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Service\EventActions;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserFixture extends Fixture
{
    private EventActions $eventActions;

    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHashed,
        EventActions $eventActions
    ) {
        $this->eventActions = $eventActions;
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

        // Create and set up the UserExternalAuth entity
        $userExternalAuth = new UserExternalAuth();
        $userExternalAuth->setUser($admin);
        $userExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT);
        $userExternalAuth->setProviderId(UserProvider::EMAIL);
        $manager->persist($userExternalAuth);

        // Save the event Action using the service
        $this->eventActions->saveEvent($admin, AnalyticalEventType::ADMIN_CREATION, new DateTime(), []);
        $this->eventActions->saveEvent($admin, AnalyticalEventType::ADMIN_VERIFICATION, new DateTime(), []);


        $manager->flush();
    }
}
