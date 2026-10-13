<?php

namespace App\Controller\Installation;

use App\DTO\CaptchaDTO;
use App\Entity\InstallationProgress;
use App\Enum\AdminRoleType;
use App\Enum\InstallationStep;
use App\Exception\EncryptionException;
use App\Form\CaptchaStepType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
#[IsGranted(UserAuthenticationVoter::INSTALLATION_WRITE)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class CaptchaStepController extends AbstractController
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
        '/captcha',
        name: 'admin_dashboard_settings_certs_installation_captcha',
        methods: ['GET', 'POST']
    )]
    public function __invoke(Request $request): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();
        if (!$lastInstallation instanceof InstallationProgress) {
            return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
        }

        $computedStep = $this->installationService->getStep($lastInstallation);


        // Guard: Redirect if current computed step is NOT CAPTCHA
        $redirect = $this->installationFlow->redirectIfStep(
            $computedStep,
            InstallationStep::DATABASE,
            InstallationStep::SETTINGS,
            InstallationStep::SECURITY_TXT,
            InstallationStep::ADMIN,
            InstallationStep::COMMAND,
            InstallationStep::COMPLETED,
        );

        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $dto = new CaptchaDTO();

        $form = $this->createForm(CaptchaStepType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->installationService->saveCaptchaSettings($dto, $lastInstallation);

                $this->addFlash(
                    'success',
                    $this->translator->trans('captcha.saveSuccess', [], 'controllers')
                );

                // Dynamically calculates the next step (which will now be SECURITY_TXT)
                $nextStep = $this->installationService->getStep($lastInstallation);

                return $this->installationFlow->redirectTo($nextStep);
            } catch (Throwable) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('captcha.saveError', [], 'controllers')
                );
            }
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/captcha.html.twig',
            [
                'data' => $this->getSettings->getSettings(),
                'form' => $form->createView(),
                'formDTO' => $dto,
                'stages' => $this->installationService->getStepperStatus($computedStep, $lastInstallation),
            ]
        );
    }
}
