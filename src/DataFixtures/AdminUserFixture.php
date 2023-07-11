<?php

namespace App\DataFixtures;


use App\Entity\User;
use DateTime;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserFixture extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHashed
    ) {
    }

    public function load(ObjectManager $manager)
    {
        $admin = new User();
        $admin->setUuid('admin');
        $admin->setEmail('admin@example.com');
        $admin->setPassword($this->userPasswordHashed->hashPassword($admin, 'pancakes'));
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);
        $admin->setCreatedAt(new DateTime());

        // Create 10 additional users, this is only for testing the pagination system
        /*
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setUuid('user'.$i);
            $user->setEmail('user'.$i.'@example.com');
            $user->setPassword($this->userPasswordHashed->hashPassword($admin, 'pancakes'));
            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(true);
            $user->setCreatedAt(new DateTime());

            $manager->persist($user);
        }
        */

        $manager->persist($admin);
        $manager->flush();
    }
}
