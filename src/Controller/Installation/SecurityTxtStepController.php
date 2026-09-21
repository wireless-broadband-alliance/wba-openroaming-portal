<?php

namespace App\Controller\Installation;

use App\DTO\SecurityTxtDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\InstallationStep;
use App\Enum\ProcessStatusType;
use App\Exception\EncryptionException;
use App\Form\SecurityTxtType;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class SecurityTxtStepController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly InstallationService $installationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly InstallationFlow $installationFlow,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route('/security', name: 'admin_dashboard_settings_certs_installation_security_txt')]
    public function __invoke(Request $request): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();

        if (!$lastInstallation instanceof InstallationProgress) {
            // Upgrade path: completed installation without security.txt settings
            if (!$this->installationService->isSecurityStepPending()) {
                return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
            }
            $lastInstallation = $this->installationService->startSecurityStepFromLastCompleted();
            if (!$lastInstallation instanceof InstallationProgress) {
                return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
            }
        }

        $step = $this->installationService->getStep($lastInstallation);
        $redirect = $this->installationFlow->redirectIfStep(
            $step,
            InstallationStep::DATABASE,
            InstallationStep::SETTINGS,
        );
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $data = $this->getSettings->getSettings();

        $dto = new SecurityTxtDTO();
        $form = $this->createForm(SecurityTxtType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $this->installationService->saveSecurityTxtSettings($dto);

            $lastInstallation->setUpdatedAt(new DateTime());
            $lastInstallation->setInstallationState(ProcessStatusType::IN_PROGRESS);
            $this->entityManager->persist($lastInstallation);
            $this->entityManager->flush();

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::INSTALLATION_SECURITY_TXT_CONFIG->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $user->getUuid(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('securityTxtSaved', [], 'controllers')
            );

            // The summary route dispatches to whichever step is still missing
            return $this->installationFlow->redirectTo(InstallationStep::COMPLETED);
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/security_txt.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $dto,
                'stages' => $this->installationService->getStepperStatus($step),
            ]
        );
    }
}
