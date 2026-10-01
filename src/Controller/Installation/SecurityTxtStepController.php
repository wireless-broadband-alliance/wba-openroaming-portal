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
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class SecurityTxtStepController extends AbstractController
{
    public function __construct(
        private readonly InstallationService $installationService,
        private readonly InstallationFlow $installationFlow,
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route(
        '/security-txt',
        name: 'admin_dashboard_settings_certs_installation_security_txt',
        methods: [
            'GET',
            'POST',
        ]
    )]
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

            $securityExpires = $lastInstallation->getSecurityExpires();
            $dto->expires = $securityExpires instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($securityExpires)
                : null;

            $dto->pgpFingerprint = $lastInstallation->getSecurityPgpFingerprint();
        }

        $form = $this->createForm(SecurityTxtType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->installationService->saveSecurityTxtSettings($dto, $lastInstallation);

                    $this->addFlash(
                        'success',
                        $this->translator->trans('securityTxt.saveSuccess', [], 'controllers')
                    );

                    // Re-calculate the next step dynamically!
                    $nextStep = $this->installationService->getStep($lastInstallation);

                    return $this->installationFlow->redirectTo($nextStep);
                } catch (Throwable) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('securityTxt.saveError', [], 'controllers')
                    );
                }
            }
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/security_txt.html.twig',
            [
                'data' => $this->getSettings->getSettings(),
                'form' => $form->createView(),
                'formDTO' => $dto,
                'stages' => $this->installationService->getStepperStatus($computedStep, $lastInstallation),
            ]
        );
    }
}
