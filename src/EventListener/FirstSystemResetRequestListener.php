<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\CertificateTestResult;
use App\Enum\ProcessStatusType;
use App\Repository\CertificateSetupProcessRepository;
use App\Repository\InstallationProgressRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: InteractiveLoginEvent::class)]
readonly class FirstSystemResetRequestListener
{
    public function __construct(
        private Security $security,
        private InstallationProgressRepository $installationProgressRepository,
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator
    ) {
    }

    public function __invoke(InteractiveLoginEvent $event): void
    {
        $session = $event->getRequest()->getSession();
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof User || !$this->security->isGranted('ROLE_ADMIN', $user)) {
            return;
        }

        $completedInstallation = $this->installationProgressRepository->findOneBy([
            'installationState' => ProcessStatusType::COMPLETED
        ]);

        if (!$completedInstallation) {
            $session->set('2fa_verified_dashboard', true);
            $session->set('system_reset_request', 'admin_dashboard_settings_certs_installation');
            $this->handleRedirect(
                $event,
                $session,
                'session_installation_started',
                $this->translator->trans(
                    'missingInstallationProcess',
                    [],
                    'eventListener'
                ),
                'admin_dashboard_settings_certs_installation'
            );
            return;
        }

        $completedCertificates = $this->certificateSetupProcessRepository->getLatestProcess();
        if (!$completedCertificates) {
            $session->set('2fa_verified_dashboard', true);
            $session->set('system_reset_request', 'admin_dashboard_settings_certs_radsecproxy_upload');
            $this->handleRedirect(
                $event,
                $session,
                'session_certificate_started',
                $this->translator->trans(
                    'missingCertificateProcess',
                    [],
                    'eventListener'
                ),
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            return;
        }

        if ($completedCertificates->getRadsecproxyTestResult() === null) {
            $session->set('2fa_verified_dashboard', true);
            $session->set('system_reset_request', 'admin_dashboard_settings_certs_radsecproxy_upload');
            $this->handleRedirect(
                $event,
                $session,
                'session_certificate_started',
                $this->translator->trans(
                    'certificateProcessPending',
                    [],
                    'eventListener'
                ),
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            return;
        }

        if ($completedCertificates->getRadsecproxyTestResult() === CertificateTestResult::PASSED) {
            $session->set('2fa_verified_dashboard', true);
            $session->set('system_reset_request', 'admin_dashboard_settings_certs_management_freeradius_selection');
            $this->handleRedirect(
                $event,
                $session,
                'session_certificate_started',
                $this->translator->trans(
                    'certificateProcessPending',
                    [],
                    'eventListener'
                ),
                'admin_dashboard_settings_certs_management_freeradius_selection'
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
