<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CertificateFreeradiusUploadDTO;
use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateRouteAccess;
use App\Enum\FirewallType;
use App\Form\CertificateFreeradiusUploadType;
use App\Form\SimpleSubmitFormType;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateStorageService;
use App\Service\GetSettings;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class CertificateFreeradiusManagementController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService,
        private readonly CertificateStorageService $certificateStorageService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/upload',
        name: 'admin_dashboard_settings_certs_freeradius_upload'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementFreeradiusUpload(
        Request $request
    ): Response {
        if ($redirect = $this->enforceStageAccess(CertificateRouteAccess::FREERADIUS_UPLOAD)) {
            return $redirect;
        }

        // Get current process state
        $processState = $this->certificateProcessCheckerService->getProcessState();

        // If there's no active process
        if (!$processState['active']) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'noActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                )
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_upload');
        }

        $data = $this->getSettings->getSettings();

        // Prepare DTO
        $certificateUploadDTO = new CertificateFreeradiusUploadDTO();

        // Create & handle form
        $form = $this->createForm(CertificateFreeradiusUploadType::class, $certificateUploadDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $process = $this->certificateProcessCheckerService->getCurrentProcess();
            // In case there's not active process
            if (!$process instanceof CertificateSetupProcess) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('noActiveProcess', [], 'CertificateProcessCheckerService')
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_upload');
            }

            // Check if the uploaded cert is a EV
            if (in_array('CERTIFICATE_NOT_EV_WARNING', $certificateUploadDTO->notices, true)) {
                $process->setIsFreeradiusCertEV(false);
                $this->addFlash(
                    'warning',
                    $this->translator->trans('not_ev_warning', [], 'controllers')
                );
            } else {
                $process->setIsFreeradiusCertEV(true);
            }

            if (in_array('CERTIFICATE_LETS_ENCRYPT_WARNING', $certificateUploadDTO->notices, true)) {
                $process->setIsFreeradiusCertEV(false);
                $this->addFlash(
                    'warning',
                    $this->translator->trans('cert_is_lets_encrypt_warning', [], 'controllers')
                );
            } else {
                $process->setIsFreeradiusCertEV(true);
            }

            if ($certificateUploadDTO->ca instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->ca,
                    CertificateMachineType::FREERADIUS->value,
                    CertificateFileName::CA_PEM->value,
                    $process
                );
            }

            if ($certificateUploadDTO->cert instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->cert,
                    CertificateMachineType::FREERADIUS->value,
                    CertificateFileName::CERT_PEM->value,
                    $process
                );
            }

            if ($certificateUploadDTO->chain instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->chain,
                    CertificateMachineType::FREERADIUS->value,
                    CertificateFileName::CHAIN_PEM->value,
                    $process
                );
            }

            if ($certificateUploadDTO->fullChain instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->fullChain,
                    CertificateMachineType::FREERADIUS->value,
                    CertificateFileName::FULL_CHAIN_PEM->value,
                    $process
                );
            }

            if ($certificateUploadDTO->privKey instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->privKey,
                    CertificateMachineType::FREERADIUS->value,
                    CertificateFileName::PRIVATE_KEY_PEM->value,
                    $process,
                    true
                );
            }

            // After the files are validated and the processed, update them once again to add
            $process->setFreeradiusFormCompletedAt(new DateTimeImmutable());
            $process->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            $this->addFlash(
                'success',
                $this->translator->trans(
                    'freeradiusCertUploadedSuccessfully',
                    [],
                    'controllers'
                )
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_freeradius_config');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/upload.html.twig',
            [
                'data' => $data,
                'certificateUploadDTO' => $certificateUploadDTO,
                'form' => $form->createView(),
                'context' => FirewallType::DASHBOARD->value,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/config',
        name: 'admin_dashboard_settings_certs_freeradius_config'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementFreeradiusConfig(Request $request): Response
    {
        if ($redirect = $this->enforceStageAccess(CertificateRouteAccess::FREERADIUS_CONFIG)) {
            return $redirect;
        }

        // Get current process state
        $processState = $this->certificateProcessCheckerService->getProcessState();
        $process = $processState['process'] ?? null;

        // If there's no active process
        if (!$processState['active']) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'noActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                )
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_upload');
        }

        // Fetch any data/settings needed for the page
        $data = $this->getSettings->getSettings();
        // $commands = $this->certificateFreeradiusCommandsService->getRenewCommands(); TODO MAKE THIS FUNCTION AND LOGIC

        // Form handling
        $form = $this->createForm(SimpleSubmitFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($process?->getRadsecproxyConfigAppliedAt() instanceof DateTimeImmutable) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('configAlreadyApplied', [], 'controllers')
                );
            } elseif ($form->isValid()) {
                $process->setRadsecproxyConfigAppliedAt(new DateTimeImmutable());
                $process->setUpdatedAt(new DateTimeImmutable());

                $this->entityManager->persist($process);
                $this->entityManager->flush();

                $this->addFlash(
                    'success',
                    $this->translator->trans('freeradiusConfigAppliedSuccessfully', [], 'controllers')
                );

                // Redirect to the next stage automatically
                return $this->redirectToRoute(
                    'admin_dashboard_settings_certs_freeradius_test',
                );
            }
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/config.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                // 'commands' => $commands,
                'processState' => $processState,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/test',
        name: 'admin_dashboard_settings_certs_freeradius_test'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementFreeradiusTest(Request $request): Response
    {
        // Get current process state
        $requestedStage = CertificateRouteAccess::from(
            str_replace('admin_dashboard_settings_certs_', '', $request->attributes->get('_route'))
        );

        if ($this->certificateProcessCheckerService->isRouteBehindProcess($requestedStage)) {
            $this->addFlash(
                'error',
                $this->translator->trans('cannotReturnToPreviousPhase', [], 'controllers')
            );

            // Send user to the first Freeradius step ALWAYS
            return $this->redirectToRoute('admin_dashboard_settings_certs_freeradius_upload');
        }

        // Get current process state
        $processState = $this->certificateProcessCheckerService->getProcessState();

        // If no active process, redirect to the first stage or fallback
        if (!$processState['active']) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'noActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                )
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_upload');
        }

        // Fetch settings/data needed for the page
        $data = $this->getSettings->getSettings();

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/test.html.twig',
            [
                'data' => $data,
                'processState' => $processState,
                'process' => $processState['process'],
            ]
        );
    }

    private function enforceStageAccess(CertificateRouteAccess $requestedStage): ?Response
    {
        // Check if user can access this stage
        if (!$this->certificateProcessCheckerService->canAccessStage($requestedStage)) {
            $this->addFlash(
                'warning',
                $this->translator->trans('cannotAccessStageYet', [], 'controllers')
            );
            $nextRoute = $this->certificateProcessCheckerService->getNextRequiredRoute(
                $this->certificateProcessCheckerService->getProcessState()['stages'] ?? []
            );
            return $this->redirectToRoute($nextRoute ?? CertificateRouteAccess::FREERADIUS_UPLOAD->routeName());
        }

        // Prevent going back to previous phase
        if ($this->certificateProcessCheckerService->isRouteBehindProcess($requestedStage)) {
            $this->addFlash('warning', $this->translator->trans('cannotReturnToPreviousPhase', [], 'controllers'));
            return $this->redirectToRoute(CertificateRouteAccess::FREERADIUS_UPLOAD->routeName());
        }

        // Reset later Freeradius steps if user navigated backwards
        $process = $this->certificateProcessCheckerService->getCurrentProcess();
        $currentStage = $this->certificateProcessCheckerService->getProcessCurrentStage();
        if ($process && $currentStage instanceof CertificateRouteAccess) {
            $requestedIndex = $this->certificateProcessCheckerService->indexOf($requestedStage);
            $currentIndex = $this->certificateProcessCheckerService->indexOf($currentStage);

            if ($requestedIndex >= 0 &&
                $requestedIndex < $currentIndex &&
                $requestedStage->phase() === 'freeradius' &&
                $currentStage->phase() === 'freeradius') {
                $this->certificateProcessCheckerService->resetStagesFrom($requestedStage);
                $this->entityManager->persist($process);
                $this->entityManager->flush();
            }
        }

        return null; // no redirect, proceed
    }
}
