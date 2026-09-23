<?php

namespace App\Controller\Installation;

use App\Entity\InstallationProgress;
use App\Enum\AdminRoleType;
use App\Enum\InstallationStep;
use App\Exception\EncryptionException;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/summary.html.twig',
            [
                'data' => $this->getSettings->getSettings(),
                'Installation' => $this->installationService->fillDto($lastInstallation),
                'stages' => $this->installationService->getStepperStatus($computedStep, $lastInstallation),
            ]
        );
    }
}
