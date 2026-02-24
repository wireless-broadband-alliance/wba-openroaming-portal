<?php

namespace App\Service;

use App\DTO\UserAddDTO;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AdminPermissionsType;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class UserCreationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventActions $eventActions,
        private UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    /**
     * @throws RandomException
     */
    public function createUser(User $user, string $password, string $provider, Request $request): User
    {
        $userAuths = new UserExternalAuth();

        // Set the hashed password for the user
        $user->setPassword($password);
        $user->setTwoFAcode((string)random_int(100000, 999999));
        $user->setTwoFAcodeGeneratedAt(new DateTime());
        $user->setTwoFAcodeIsActive(true);
        $user->setCreatedAt(new DateTime());
        $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
        $userAuths->setProviderId($provider);
        $userAuths->setUser($user);
        $this->entityManager->persist($user);
        $this->entityManager->persist($userAuths);
        $this->entityManager->flush();

        // Defines the Event to the table
        $eventMetaData = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => PlatformMode::LIVE->value,
            'uuid' => $user->getUuid(),
            'registrationType' => $provider,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION->value,
            new DateTime(),
            $eventMetaData
        );

        return $user;
    }

    public function setEmail(string $email, User $user): User
    {
        $user->setEmail($email);
        $user->setUuid($email);

        return $user;
    }

    public function setPhoneNumber(User $user): User
    {
        if (!is_null($user->getPhoneNumber())) {
            $user->setUuid(
                "+" . $user->getPhoneNumber()->getCountryCode() . $user->getPhoneNumber()->getNationalNumber()
            );
        }
        return $user;
    }

    /**
     * Maps the DTO data back to the User entity
     * @throws RandomException
     */
    public function createAdminUser(UserAddDTO $userAddDTO): User
    {
        $user = new User();
        $userAuths = new UserExternalAuth();

        if ($userAddDTO->accountType === UserProvider::EMAIL->value && $userAddDTO->email) {
            $user->setEmail($userAddDTO->email);
            $user->setUuid($userAddDTO->email);
            $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
            $userAuths->setProviderId(UserProvider::EMAIL->value);
            $userAuths->setUser($user);
        } elseif ($userAddDTO->accountType === UserProvider::PHONE_NUMBER->value && $userAddDTO->phoneNumber) {
            $user->setPhoneNumber($userAddDTO->phoneNumber);
            $user->setUuid(
                '+' .
                $userAddDTO->phoneNumber->getCountryCode() .
                $userAddDTO->phoneNumber->getNationalNumber()
            );
            $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
            $userAuths->setProviderId(UserProvider::PHONE_NUMBER->value);
            $userAuths->setUser($user);
        }

        $user->setRoles([AdminRoleType::ROLE_ADMIN->value]);
        $user->setFirstName($userAddDTO->firstName);
        $user->setLastName($userAddDTO->lastName);
        $user->setForgotPasswordRequest(true);
        $user->setTwoFAcode((string)random_int(100000, 999999));
        $user->setTwoFAcodeGeneratedAt(new DateTime());
        $user->setTwoFAcodeIsActive(true);
        $user->setCreatedAt(new DateTime());

        // Hash the password
        $hashedPassword = $this->userPasswordHasher->hashPassword($user, $userAddDTO->password);
        $user->setPassword($hashedPassword);

        // Set permissions
        $adminPermissions = $userAddDTO->adminPermissions();

        // Example ["USER_ENGAGEMENT_WRITE", ...]
        $permissionsArray = array_map(static fn(AdminPermissionsType $p) => $p->value, $adminPermissions);
        $user->setPermissions($permissionsArray);

        // Persist new user
        $this->entityManager->persist($user);
        $this->entityManager->persist($userAuths);
        $this->entityManager->flush();

        return $user;
    }
}
