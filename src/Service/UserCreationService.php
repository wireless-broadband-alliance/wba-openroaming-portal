<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class UserCreationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
    ) {
    }

    public function createUserMagicLink(User $user, string $password, Request $request): User {
        $userAuths = new UserExternalAuth();

        // Set the hashed password for the user
        $user->setPassword($password);
        $user->setTwoFAcode(random_int(100000, 999999));
        $user->setTwoFAcodeGeneratedAt(new DateTime());
        $user->setTwoFAcodeIsActive(true);
        $user->setCreatedAt(new DateTime());
        $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
        $userAuths->setProviderId(UserProvider::EMAIL->value);
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
            'registrationType' => UserProvider::EMAIL->value,
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::USER_CREATION->value,
            new DateTime(),
            $eventMetaData
        );

        return $user;
    }
}