<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\TwoFAType;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
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
        private SettingRepository $settingRepository,
        private GetSettings $getSettings
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
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

        if ($userToken && str_starts_with($path, '/dashboard')) {
            // Make an exception to ignore the '/dashboard/login' route
            if ($path === '/dashboard/login') {
                return;
            }

            $user = $this->userRepository->find($userToken->getId());
            if (!$user) {
                throw new AccessDeniedHttpException('Access denied.');
            }

            // Check if the 2FA process is completed
            if (
                ($user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value)
                && !$session->has('2fa_verified')
            ) {
                $url = $this->router->generate('app_login');
                $event->setResponse(new RedirectResponse($url));
            }

            $setting2faStatus = $data['TWO_FACTOR_AUTH_STATUS']['value'];
            if (
                $setting2faStatus !== TwoFAType::NOT_ENFORCED->value &&
                $user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value
            ) {
                $url = $this->router->generate('app_configure2FA');
                $event->setResponse(new RedirectResponse($url));
            }
        }
    }
}
