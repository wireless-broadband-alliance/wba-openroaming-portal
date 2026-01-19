<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class EnforcePasswordResetService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private EventActions $eventActions,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     */
    public function enforceReset(User $adminUser, string $ip, string $user_agent): void
    {
        $localUsers = $this->userRepository->findAllPortalAccountsExcludingAdmin();
        foreach ($localUsers as $user) {
            $user->setForgotPasswordRequest(true);
            $this->entityManager->persist($user);

            $eventMetadata = [
                'ip' => $ip,
                'user_agent' => $user_agent,
                'edited ' => $user->getUuid(),
                'by' => $adminUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $adminUser,
                AnalyticalEventType::ADMIN_CHANGED_LOGIN_WITH_UUID_ONLY->value,
                new DateTime(),
                $eventMetadata
            );
        }

        $this->entityManager->flush();
    }
}
