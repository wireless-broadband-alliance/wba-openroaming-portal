<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Enum\TwoFAType;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\TwoFAService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

readonly class SessionValidatorListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router,
        private UserRepository $userRepository,
        private GetSettings $getSettings,
        private TwoFAService $twoFAService,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $this->getSettings->getSettings();
        $request = $event->getRequest();
        $session = $request->getSession();
        $path = $request->getPathInfo();

        // Check if the user is authenticated
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        /** @var User $userToken */
        $userToken = $token->getUser();
        $url = [
            '/dashboard/login',
            '/dashboard/verify2FA',
            '/dashboard/verify2FA/TOTP',
            '/dashboard/generate2FACode',
            '/dashboard/verify2FA/resend',
            '/dashboard/configure2FA',
            '/dashboard/enable2FA/TOTP',
            '/dashboard/2FAFirstSetup/portal',
            '/dashboard/2FAFirstSetup/verification',
            '/dashboard/enable2FA/TOTP',
            '/dashboard/2FAFirstSetup/codes',
            '/dashboard/2FAFirstSetup/codes/save',
            '/dashboard/downloadCodes',
            '/dashboard/generate2FACode/swapMethod',
            '/dashboard/2FASwapMethod/disable/TOTP',
            '/dashboard/2FASwapMethod/disableLocal',
            '/dashboard/disable2FA/resend',
            '/dashboard/enable2FA/resend',
            '/dashboard/validate2FA/resend',
        ];
        if ($userToken && str_starts_with($path, '/dashboard')) {
            // Make an exception to ignore the '/dashboard/login' route
            if (in_array($path, $url)) {
                return;
            }

            $user = $this->userRepository->find($userToken->getId());
            if (!$user) {
                throw new AccessDeniedHttpException('Access denied.');
            }

            // Check if the 2FA process is completed
            if (
                ($user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value)
                && !$session->has('2fa_verified_dashboard')
            ) {
                if (
                    $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::EMAIL->value ||
                    $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::SMS->value
                ) {
                    $url = $this->router->generate('app_2FA_generate_code', [
                        'context' => FirewallType::DASHBOARD->value,
                    ]);
                } elseif ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::TOTP->value) {
                    $url = $this->router->generate('app_verify2FA_TOTP', [
                        'context' => FirewallType::DASHBOARD->value,
                    ]);
                } else {
                    $url = $this->router->generate('app_landing');
                }
                $event->setResponse(new RedirectResponse($url));
            }
            if (
                $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value
            ) {
                $url = $this->router->generate('app_configure2FA', ['context' => FirewallType::DASHBOARD->value]);
                $event->setResponse(new RedirectResponse($url));
            }
            if (
                !$this->twoFAService->hasValidOTPCodes($user) &&
                $user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value
            ) {
                $url = $this->router->generate('app_otpCodes', ['context' => FirewallType::DASHBOARD->value]);
                $event->setResponse(new RedirectResponse($url));
            }
        }
    }
}
