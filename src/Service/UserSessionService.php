<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\SessionStatus;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Single place that knows which session keys belong to the logged-in user.
 * Used on logout and whenever a controller logs a user in manually, so state from a
 * previous account in the same browser session never leaks into the next one.
 */
final readonly class UserSessionService
{
    /**
     * Keys tied to the authenticated user (verification, 2FA, password reset, UUID-only login).
     */
    private const array USER_KEYS = [
        SessionStatus::VERIFIED,
        SessionStatus::AUTHENTICATED_VIA_UUID_ONLY,
        SessionStatus::TWO_FACTOR_CONTEXT,
        SessionStatus::FORGOT_PASSWORD_UUID,
        SessionStatus::SYSTEM_RESET_REQUEST,
    ];

    /**
     * Keys tied to the installation wizard / certificate flows.
     */
    private const array PROCESS_KEYS = [
        SessionStatus::INSTALLATION_STARTED,
        SessionStatus::CERTIFICATE_STARTED,
        SessionStatus::INSTALLATION_VERIFICATION,
        SessionStatus::CERTIFICATE_VERIFICATION,
    ];

    public function __construct(
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Removes every per-user flag for the given firewall.
     */
    public function clearUserState(string $firewallName, ?SessionInterface $session = null): void
    {
        $session ??= $this->requestStack->getSession();

        foreach (self::USER_KEYS as $key) {
            $session->remove($key->value);
        }

        $session->remove('2fa_verified_' . $firewallName);
    }

    /**
     * Full cleanup used on logout: user flags plus installation/certificate process flags.
     */
    public function clearAll(string $firewallName, ?SessionInterface $session = null): void
    {
        $session ??= $this->requestStack->getSession();

        $this->clearUserState($firewallName, $session);

        foreach (self::PROCESS_KEYS as $key) {
            $session->remove($key->value);
        }
    }

    /**
     * Logs a user in manually (registration, magic link, code confirmation...) with a clean session.
     * Also regenerates the session ID to prevent session fixation.
     */
    public function loginUser(User $user, string $firewallName): UsernamePasswordToken
    {
        $session = $this->requestStack->getSession();

        $this->clearUserState($firewallName, $session);
        $session->migrate(true);

        $token = new UsernamePasswordToken($user, $firewallName, $user->getRoles());
        $this->tokenStorage->setToken($token);
        $session->set('_security_' . $firewallName, serialize($token));

        return $token;
    }
}
