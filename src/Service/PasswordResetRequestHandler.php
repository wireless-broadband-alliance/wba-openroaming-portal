<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Enum\PlatformMode;
use App\Enum\AnalyticalEventType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class PasswordResetRequestHandler
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $entityManager,
        private EventActions $eventActions,
        private RequestStack $requestStack
    ) {
    }

    /**
     * @throws RandomException
     */
    public function handle(User $user): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Authenticate the user
        $token = new UsernamePasswordToken($user, FirewallType::LANDING->value, $user->getRoles());
        $this->tokenStorage->setToken($token);

        $event = new InteractiveLoginEvent($request, $token);
        $this->eventDispatcher->dispatch($event);

        // Update user
        $user->setForgotPasswordRequest(true);
        $user->setVerificationCode(random_int(100000, 999999));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Kill the session forgot_password_uuid after being used
        $request->getSession()->remove('forgot_password_uuid');

        // Log the event
        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => PlatformMode::LIVE->value,
            'uuid' => $user->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::FORGOT_PASSWORD_REQUEST_ACCEPTED->value,
            new DateTime(),
            $eventMetadata
        );
    }
}
