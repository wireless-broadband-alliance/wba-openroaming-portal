<?php

namespace App\EventListener;

use App\Entity\CertificateSetupProcess;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\CertificateTestResult;
use App\Enum\ProcessStatusType;
use App\Enum\SessionStatus;
use App\Repository\CertificateSetupProcessRepository;
use App\Repository\InstallationProgressRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::REQUEST)]
readonly class AdminCertificateProcessEnforcerListener
{
    public function __construct(
        private Security $security,
        private InstallationProgressRepository $installationProgressRepository,
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        $path = $request->getPathInfo();

        $user = $this->security->getUser();

        // Only admins with valid session flag
        if (
            !$user instanceof User ||
            !$this->security->isGranted('ROLE_ADMIN') ||
            !$session->has(SessionStatus::SYSTEM_RESET_REQUEST->value)
        ) {
            return;
        }

        // Only intercept certificate management routes
        if (!str_starts_with($path, '/dashboard')) {
            return;
        }

        /**
         * STRICT WHITELIST OF ALLOWED PAGES
         * Supports exact matches and regex patterns
         */
        $allowedPatterns = [
            // Installation pages
            '#^/dashboard/settings/certificatesManagement/installation$#',
            '#^/dashboard/settings/certificatesManagement/installation/commands$#',
            '#^/dashboard/settings/certificatesManagement/installation/settings$#',
            '#^/dashboard/settings/certificatesManagement/installation/admin$#',
            '#^/dashboard/settings/certificatesManagement/installation/admin/sendCode$#',
            '#^/dashboard/settings/certificatesManagement/installation/admin/confirmation$#',
            '#^/dashboard/settings/certificatesManagement/installation/admin/confirmation/resend$#',
            '#^/dashboard/settings/certificatesManagement/installation/summary$#',
            '#^/dashboard/settings/certificatesManagement/installation/abortProcess$#',

            // Verify identity (installation | certificates)
            '#^/dashboard/settings/certificatesManagement/verifyIdentity/(installation|certificates)$#',
            '#^/dashboard/settings/certificatesManagement/verifyIdentity/(installation|certificates)/code$#',
            '#^/dashboard/settings/certificatesManagement/verifyIdentity/(installation|certificates)/resend$#',

            // Certificates reset abort
            '#^/dashboard/settings/certificatesManagement/certificates/abort$#',

            // Radsecproxy steps
            '#^/dashboard/settings/certificatesManagement/radsecproxy/upload$#',
            '#^/dashboard/settings/certificatesManagement/radsecproxy/config$#',
            '#^/dashboard/settings/certificatesManagement/radsecproxy/test$#',
            '#^/dashboard/settings/certificatesManagement/radsecproxy/test/run$#',

            // Freeradius Certificates Management Selection
            '#^/dashboard/settings/certificatesManagement/freeradius/selection#',

            // Freeradius steps
                // Auto Generation Lets Encrypt
            '#^/dashboard/settings/certificatesManagement/freeradius/autoRenew$#',
            '#^/dashboard/settings/certificatesManagement/freeradius/autoRenewDomain#',
                // Manual Upload
            '#^/dashboard/settings/certificatesManagement/freeradius/upload$#',
            '#^/dashboard/settings/certificatesManagement/freeradius/config$#',
            '#^/dashboard/settings/certificatesManagement/freeradius/test$#',
            '#^/dashboard/settings/certificatesManagement/freeradius/test/run$#',
        ];
        $allowed = array_any(
            $allowedPatterns,
            fn(string $pattern): bool => preg_match($pattern, $path) === 1
        );

        // If path IS allowed → do nothing
        if ($allowed) {
            return;
        }

        // If NOT allowed → force process enforcement
        $this->enforceProcess($event, $session);
    }

    private function enforceProcess(RequestEvent $event, SessionInterface $session): void
    {
        // Check installation progress
        $installation = $this->installationProgressRepository->findOneBy([
            'installationState' => ProcessStatusType::COMPLETED
        ]);

        if (!$installation instanceof InstallationProgress) {
            $session->set(
                SessionStatus::SYSTEM_RESET_REQUEST->value,
                'admin_dashboard_settings_certs_installation'
            );
            $this->redirectTo($event, 'admin_dashboard_settings_certs_installation');
            return;
        }

        // Check certificates progress
        $certProcess = $this->certificateSetupProcessRepository->getLatestProcess();

        if (!$certProcess instanceof CertificateSetupProcess) {
            $session->set(
                SessionStatus::SYSTEM_RESET_REQUEST->value,
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            $this->redirectTo($event, 'admin_dashboard_settings_certs_radsecproxy_upload');
            return;
        }

        // Radsecproxy test required
        if (!$certProcess->getRadsecproxyTestResult() instanceof CertificateTestResult) {
            $session->set(
                SessionStatus::SYSTEM_RESET_REQUEST->value,
                'admin_dashboard_settings_certs_radsecproxy_upload'
            );
            $this->redirectTo($event, 'admin_dashboard_settings_certs_radsecproxy_upload');
            return;
        }

        // If radsec is OK → next step is freeradius
        if ($certProcess->getRadsecproxyTestResult() === CertificateTestResult::PASSED) {
            $session->set(
                SessionStatus::SYSTEM_RESET_REQUEST->value,
                'admin_dashboard_settings_certs_management_freeradius_selection'
            );
            $this->redirectTo($event, 'admin_dashboard_settings_certs_management_freeradius_selection');
            return;
        }

        // Process fully complete
        $session->remove(SessionStatus::SYSTEM_RESET_REQUEST->value);
    }

    private function redirectTo(RequestEvent $event, string $routeName): void
    {
        $url = $this->urlGenerator->generate($routeName);
        $event->setResponse(new RedirectResponse($url));
    }
}
