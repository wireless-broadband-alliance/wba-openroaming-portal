<?php

namespace App\EventListener;

use App\Entity\CertificateSetupProcess;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\CertificateTestResult;
use App\Enum\ProcessStatusType;
use App\Enum\SessionStatus;
use App\Repository\CertificateSetupProcessRepository;
use App\Repository\InstallationProgressRepository;
use App\Service\CertificateProcessCheckerService;
use App\Service\InstallationService;
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
        private TranslatorInterface $translator,
        private InstallationService $installationService,
        private CertificateProcessCheckerService $certificateProcessCheckerService,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(InteractiveLoginEvent $event): void
    {
        $session = $event->getRequest()->getSession();
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof User || !$this->security->isGranted(AdminRoleType::ROLE_ADMIN->value, $user)) {
            return;
        }
        if (!($this->installationProgressRepository->getLast() instanceof InstallationProgress)) {
            $this->installationService->verifyEnvSettings();
        }

        $completedInstallation = $this->installationProgressRepository->findOneBy([
            'installationState' => ProcessStatusType::COMPLETED
        ]);

        if (!$completedInstallation instanceof InstallationProgress) {
            $session->set('2fa_verified_dashboard', true);
            $session->set(SessionStatus::SYSTEM_RESET_REQUEST->value, 'admin_dashboard_settings_certs_installation');
            $this->handleRedirect(
                $event,
                $session,
                $this->translator->trans(
                    'missingInstallationProcess',
                    [],
                    'eventListener'
                ),
                'admin_dashboard_settings_certs_installation'
            );
            return;
        }

        if (!($this->certificateSetupProcessRepository->getLatestProcess() instanceof CertificateSetupProcess)) {
            $certProcess = $this->certificateProcessCheckerService->verifyCertificates();
            if (
                $certProcess instanceof CertificateSetupProcess &&
                $certProcess->getStatus() === ProcessStatusType::COMPLETED
            ) {
                $this->handleRedirect(
                    $event,
                    $session,
                    $this->translator->trans(
                        'certificateProcessPending',
                        [],
                        'eventListener'
                    ),
                    'admin_page'
                );
                return;
            }
        }

        if ($this->certificateSetupProcessRepository->getLatestCompletedProcess() instanceof CertificateSetupProcess) {
            return;
        }

        $completedCertificates = $this->certificateSetupProcessRepository->getLatestProcess();
        if (!$completedCertificates instanceof CertificateSetupProcess) {
            $session->set('2fa_verified_dashboard', true);
            $session->set(
                SessionStatus::SYSTEM_RESET_REQUEST->value,
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            $this->handleRedirect(
                $event,
                $session,
                $this->translator->trans(
                    'missingCertificateProcess',
                    [],
                    'eventListener'
                ),
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            return;
        }

        if ($completedCertificates->getFreeradiusTestResult() !== CertificateTestResult::PASSED) {
            $session->set('2fa_verified_dashboard', true);
            $session->set(
                SessionStatus::SYSTEM_RESET_REQUEST->value,
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            $this->handleRedirect(
                $event,
                $session,
                $this->translator->trans(
                    'certificateProcessPending',
                    [],
                    'eventListener'
                ),
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            return;
        }

        // All checks are valid, remove session flags
        $session->remove(SessionStatus::INSTALLATION_STARTED->value);
        $session->remove(SessionStatus::CERTIFICATE_STARTED->value);
    }

    /**
     * Helper to set session token, flash message, and redirect
     */
    private function handleRedirect(
        InteractiveLoginEvent $event,
        SessionInterface $session,
        string $flashMessage,
        string $routeName
    ): void {
        // All checks are valid, remove session flags
        $session->set(SessionStatus::INSTALLATION_STARTED->value, true);
        $session->set(SessionStatus::INSTALLATION_VERIFICATION->value, true);
        $session->set(SessionStatus::CERTIFICATE_STARTED->value, true);
        $session->set(SessionStatus::CERTIFICATE_VERIFICATION->value, true);
        $session->getFlashBag()->add('success', $flashMessage);

        $url = $this->urlGenerator->generate($routeName);
        $response = new RedirectResponse($url);

        // Save session and flash messages
        $event->getRequest()->getSession()->save();

        // Store the redirect in request attributes so a controller/listener can handle it
        $event->getRequest()->attributes->set('_redirect', $response);
    }
}
