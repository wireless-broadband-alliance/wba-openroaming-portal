<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\DefaultUser;
use App\Enum\FirewallType;
use App\Enum\InstallationType;
use App\Enum\SessionStatus;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

readonly class InstallationListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UserRepository $userRepository,
        private RouterInterface $router,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $session = $request->getSession();

        /** @var User $userToken */
        $userToken = $token->getUser();

        $user = $this->userRepository->find($userToken->getId());

        if ($user && $user->getEmail() === DefaultUser::ADMIN->value) {
            return;
        }
        if ($user && str_starts_with($path, '/dashboard/settings/certificatesManagement/installation')) {
            if (!$session->get(SessionStatus::INSTALLATION_VERIFICATION->value)) {
                $url = $this->router->generate('admin_dashboard_settings_certs_installation_verify_send_code', [
                    'type' => InstallationType::INSTALLATION->value
                ]);
                $event->setResponse(new RedirectResponse($url));
            }
            return;
        }
        if ($user &&
            (
                str_starts_with($path, '/dashboard/settings/certificatesManagement/freeradius/') ||
                str_starts_with($path, '/dashboard/settings/certificatesManagement/radsecproxy/') ||
                str_starts_with($path, '/dashboard/settings/certificatesManagement/certificates/')
            )
        ) {
            if (!$session->get(SessionStatus::CERTIFICATE_VERIFICATION->value)) {
                $url = $this->router->generate('admin_dashboard_settings_certs_installation_verify_send_code', [
                    'type' => InstallationType::CERTIFICATES->value,
                ]);
                $event->setResponse(new RedirectResponse($url));
            }
            return;
        }
        if ($session->get(SessionStatus::INSTALLATION_VERIFICATION->value)) {
            $session->set(SessionStatus::INSTALLATION_VERIFICATION->value, false);
        }
        if ($session->get(SessionStatus::CERTIFICATE_VERIFICATION->value)) {
            $session->set(SessionStatus::CERTIFICATE_VERIFICATION->value, false);
        }
    }
}
