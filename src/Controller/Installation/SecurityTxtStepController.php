<?php

namespace App\Controller\Installation;

use App\DTO\SecurityTxtDTO;
use App\Entity\InstallationProgress;
use App\Enum\AdminRoleType;
use App\Enum\InstallationStep;
use App\Exception\EncryptionException;
use App\Form\SecurityTxtType;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class SecurityTxtStepController extends AbstractController
{
    public function __construct(
        private readonly InstallationService $installationService,
        private readonly InstallationFlow $installationFlow,
        private readonly GetSettings $getSettings,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route('/security-txt',
        name: 'admin_dashboard_settings_certs_installation_security_txt',
        methods: [
            'GET',
            'POST'
        ])]
    public function __invoke(Request $request): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();
        // If no progress record exists, attempt fallback from last completed setup
        if (!$lastInstallation instanceof InstallationProgress) {
            return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
        }

        $computedStep = $this->installationService->getStep($lastInstallation);

        // Redirect ONLY if the required step is NOT SECURITY_TXT
        $redirect = $this->installationFlow->redirectIfStep(
            $computedStep,
            InstallationStep::DATABASE,
            InstallationStep::SETTINGS,
            InstallationStep::ADMIN,
            InstallationStep::COMMAND,
            InstallationStep::COMPLETED,
        );

        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $dto = new SecurityTxtDTO();
        // Pre-fill DTO from existing entity if present
        if ($lastInstallation->getSecurityContact()) {
            $dto->contact = $lastInstallation->getSecurityContact();
            $dto->expires = $lastInstallation->getSecurityExpires();
            $dto->pgpFingerprint = $lastInstallation->getSecurityPgpFingerprint();
        }

        $form = $this->createForm(SecurityTxtType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->installationService->saveSecurityTxtSettings($dto, $lastInstallation);

                    $this->addFlash('success', 'securityTxtSaveSuccess');

                    return $this->installationFlow->redirectTo(InstallationStep::ADMIN);
                } catch (Throwable) {
                    $this->addFlash('error', 'securityTxtSaveError');
                }
            } else {
                $this->addFlash('warning', 'securityTxtFormError');
            }
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/security_txt.html.twig',
            [
                'data' => $this->getSettings->getSettings(),
                'form' => $form->createView(),
                'formDTO' => $dto,
                'stages' => $this->installationService->getStepperStatus($computedStep),
            ]
        );
    }
}
