<?php

namespace App\Controller\Installation;

use App\Entity\InstallationProgress;
use App\Enum\AdminRoleType;
use App\Enum\InstallationStep;
use App\Enum\ProcessStatusType;
use App\Enum\SessionStatus;
use App\Exception\EncryptionException;
use App\Repository\CertificateSetupProcessRepository;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class SummaryStepController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly InstallationService $installationService,
        private readonly InstallationFlow $installationFlow,
        private readonly CertificateSetupProcessRepository $certProcessRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route(
        '/summary',
        name: 'admin_dashboard_settings_certs_installation_summary',
        methods: ['GET']
    )]
    public function __invoke(): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();
        if (!$lastInstallation instanceof InstallationProgress) {
            return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
        }

        $computedStep = $this->installationService->getStep($lastInstallation);

        // Redirect away if the active step is any incomplete step prior to Summary/Completed
        $redirect = $this->installationFlow->redirectIfStep(
            $computedStep,
            InstallationStep::DATABASE,
            InstallationStep::SETTINGS,
            InstallationStep::SECURITY_TXT,
            InstallationStep::ADMIN,
            InstallationStep::COMMAND,
        );
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $hasCompletedCertProcess = $this->certProcessRepository->findOneBy([
                'status' => ProcessStatusType::COMPLETED,
            ]) !== null;

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/summary.html.twig',
            [
                'data' => $this->getSettings->getSettings(),
                'Installation' => $this->installationService->fillDto($lastInstallation),
                'stages' => $this->installationService->getStepperStatus($computedStep, $lastInstallation),
                'hasCompletedCertProcess' => $hasCompletedCertProcess,
            ]
        );
    }

    #[Route('/summary/complete', name: 'admin_dashboard_settings_certs_installation_complete', methods: [
        'POST',
        'GET'
    ])]
    public function complete(Request $request): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();

        if ($lastInstallation instanceof InstallationProgress) {
            // Mark installation as completed
            $lastInstallation->setInstallationState(ProcessStatusType::COMPLETED);
            $this->entityManager->flush();
        }

        $session = $request->getSession();

        // Remove the blocking wizard session flags
        $session->remove(SessionStatus::SYSTEM_RESET_REQUEST->value);
        $session->remove('session_installation_started');

        // Set installation as verified in session
        $session->set('installation_verification', true);

        // If a completed certificate process already exists, set certificate verification to true as well
        $hasCompletedCertProcess = $this->certProcessRepository->findOneBy([
                'status' => ProcessStatusType::COMPLETED,
            ]) !== null;

        if ($hasCompletedCertProcess) {
            $session->remove('session_certificate_started');
            $session->set('certificate_verification', true);
        }

        return $this->redirectToRoute('admin_page');
    }
}
