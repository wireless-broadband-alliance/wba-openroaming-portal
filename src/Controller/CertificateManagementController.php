<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CertificateRadSecUploadDTO;
use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateProcessStatus;
use App\Enum\FirewallType;
use App\Form\CertificateUploadType;
use App\Form\SimpleSubmitFormType;
use App\Service\CertificateCommandsService;
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

class CertificateManagementController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly CertificateStorageService $certificateStorageService,
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificateCommandsService $certificateCommandsService,
    ) {
    }

    #[Route('/dashboard/settings/certificatesManagement', name: 'admin_dashboard_settings_certs_management')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagement(): Response
    {
        $data = $this->getSettings->getSettings();

        // Check current certificateProcess status
        $processState = $this->certificateProcessCheckerService->getProcessState();

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
            'processState' => $processState,
        ]);
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/abort',
        name: 'admin_dashboard_settings_certs_management_abort',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementAbort(): Response
    {
        $process = $this->certificateProcessCheckerService->getCurrentProcess();

        // In case there's not active process
        if (!$process instanceof CertificateSetupProcess) {
            $this->addFlash(
                'error',
                $this->translator->trans('noActiveProcess', [], 'CertificateProcessCheckerService')
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_upload');
        }

        // Cancel the process and add a tag IN_COMPLETED
        // When a process is incompleted the page should ALERT the user always about this miss configured action
        $process->setStatus(CertificateProcessStatus::ABORTED);
        $process->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->persist($process);
        $this->entityManager->flush();

        $this->addFlash(
            'error',
            $this->translator->trans(
                'certificateProcessAborted',
                [],
                'controllers'
            )
        );

        return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_upload');
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/radsecproxy/upload',
        name: 'admin_dashboard_settings_certs_radsecproxy_upload'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementRadsecproxyUpload(
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();

        // Check current certificateProcess status
        $processState = $this->certificateProcessCheckerService->getProcessState();

        // Prepare DTO
        $certificateUploadDTO = new CertificateRadSecUploadDTO();

        // Create & handle form
        $form = $this->createForm(CertificateUploadType::class, $certificateUploadDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Create a certificate process before upload and making any actually changes on the DB and files
            $process = $this->certificateStorageService->createCertificateProcess();

            if ($certificateUploadDTO->client instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->client,
                    CertificateMachineType::RADSECPROXY->value,
                    CertificateFileName::CLIENT_PEM->value,
                    $process,
                    false
                );
            }

            if ($certificateUploadDTO->key instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->key,
                    CertificateMachineType::RADSECPROXY->value,
                    CertificateFileName::KEY_PEM->value,
                    $process,
                    true
                );
            }

            // After the files are validated and the processed, update them once again to add
            $process->setRadsecproxyFormCompletedAt(new DateTimeImmutable());
            $process->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            $this->addFlash(
                'success_admin',
                $this->translator->trans(
                    'radsecProxyCertUploadedSuccessfully',
                    [],
                    'controllers'
                )
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_config');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/radsecproxy/upload.html.twig',
            [
                'data' => $data,
                'certificateUploadDTO' => $certificateUploadDTO,
                'form' => $form->createView(),
                'context' => FirewallType::DASHBOARD->value,
                'processState' => $processState,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/radsecproxy/config',
        name: 'admin_dashboard_settings_certs_radsecproxy_config'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementRadsecproxyConfig(Request $request): Response
    {
        $data = $this->getSettings->getSettings();

        // Check current certificateProcess status
        $processState = $this->certificateProcessCheckerService->getProcessState();
        $process = $processState['process'] ?? [];

        // In case there's not active process
        if (!$processState['active']) {
            $this->addFlash('error', $processState['message']);
            return $this->redirectToRoute($processState['nextRoute']);
        }

        // Ensure stage access
        $redirectRoute = $this->certificateProcessCheckerService->ensureStageAccess(
            'radsecproxy_config',
            $processState['stages']
        );
        if ($redirectRoute) {
            return $this->redirectToRoute($redirectRoute);
        }

        // Return the commands to be executed on the resolver
        $commands = $this->certificateCommandsService->getRadsecproxyRenewCommands();

        $form = $this->createForm(SimpleSubmitFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($process?->getRadsecproxyConfigAppliedAt() instanceof DateTimeImmutable) {
                // Just ignore the submission and warning
                $this->addFlash(
                    'error',
                    $this->translator->trans('radsecProxyConfigAlreadyApplied', [], 'controllers')
                );
            } elseif ($form->isValid()) {
                $process->setRadsecproxyConfigAppliedAt(new DateTimeImmutable());
                $process->setUpdatedAt(new DateTimeImmutable());

                $this->entityManager->persist($process);
                $this->entityManager->flush();

                $this->addFlash(
                    'success_admin',
                    $this->translator->trans('radsecProxyConfigAppliedSuccessfully', [], 'controllers')
                );

                return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_test');
            }
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/radsecproxy/config.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'commands' => $commands,
                'processState' => $processState,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/radsecproxy/test',
        name: 'admin_dashboard_settings_certs_radsecproxy_test'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementRadsecproxyTest(): Response
    {
        $data = $this->getSettings->getSettings();

        // Check current certificateProcess status
        $processState = $this->certificateProcessCheckerService->getProcessState();

        // In case there's not active process
        if (!$processState['active']) {
            $this->addFlash('error', $processState['message']);
            return $this->redirectToRoute($processState['nextRoute']);
        }

        $redirectRoute = $this->certificateProcessCheckerService->ensureStageAccess(
            'radsecproxy_test',
            $processState['stages']
        );
        if ($redirectRoute) {
            return $this->redirectToRoute($redirectRoute);
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/radsecproxy/test.html.twig',
            [
                'data' => $data,
                'processState' => $processState,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius',
        name: 'admin_dashboard_settings_certs_freeradius'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementFreeradius(): Response
    {
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
        ]);
    }
}
