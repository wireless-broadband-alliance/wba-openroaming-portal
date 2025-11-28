<?php

namespace App\EventListener;

use App\Enum\ProcessStatusType;
use App\Repository\CertificateSetupProcessRepository;
use App\Repository\InstallationProgressRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: InteractiveLoginEvent::class)]
readonly class FirstSystemResetRequestListener
{
    public function __construct(
        private Security $security,
        private InstallationProgressRepository $installationProgressRepository,
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(InteractiveLoginEvent $event): void
    {
        $session = $event->getRequest()->getSession();
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user || !$this->security->isGranted('ROLE_ADMIN', $user)) {
            return;
        }

        $completedInstallation = $this->installationProgressRepository->findOneBy([
            'installationState' => ProcessStatusType::COMPLETED
        ]);
        if (!$completedInstallation) {
            $session->set('2fa_verified_dashboard', true);
            $session->set('first_system_reset', 'admin_dashboard_settings_certs_installation');
            $this->handleRedirect(
                $event,
                $session,
                'session_installation_started',
                'You do not have any created installation process on this portal.',
                'admin_dashboard_settings_certs_installation'
            );
            return;
        }

        $completedCertificates = $this->certificateSetupProcessRepository->getLatestProcess();
        if (!$completedCertificates) {
            $session->set('2fa_verified_dashboard', true);
            $session->set('first_system_reset', 'admin_dashboard_settings_certs_radsecproxy_upload');
            $this->handleRedirect(
                $event,
                $session,
                'session_certificate_started',
                'No active certificate process found.',
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            return;
        }

        if ($completedCertificates->getStatus() !== ProcessStatusType::COMPLETED) {
            $session->set('2fa_verified_dashboard', true);
            $session->set('first_system_reset', 'admin_dashboard_settings_certs_radsecproxy_upload');
            $this->handleRedirect(
                $event,
                $session,
                'session_certificate_started',
                'A certificate process is already in progress.',
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            return;
        }

        // All checks are valid, remove session flags
        $session->remove('session_installation_started');
        $session->remove('session_certificate_started');
    }

    /**
     * Helper to set session token, flash message, and redirect
     */
    private function handleRedirect(
        InteractiveLoginEvent $event,
        SessionInterface $session,
        string $sessionKey,
        string $flashMessage,
        string $routeName
    ): void {
        $session->set($sessionKey, true);
        $session->getFlashBag()->add('success', $flashMessage);

        $url = $this->urlGenerator->generate($routeName);
        $response = new RedirectResponse($url);

        // Save session and flash messages
        $event->getRequest()->getSession()->save();

        // Store the redirect in request attributes so a controller/listener can handle it
        $event->getRequest()->attributes->set('_redirect', $response);
    }
}
