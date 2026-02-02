<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CertificateSetupProcess;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\ProcessStatusType;
use App\Enum\SessionStatus;
use App\Repository\CertificateSetupProcessRepository;
use App\Repository\InstallationProgressRepository;
use App\Service\CertificateFreeradiusInfoService;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateRadsecproxyInfoService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\InstallationService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly InstallationProgressRepository $installationProgressRepository,
        private readonly CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private readonly EventActions $eventActions,
        private readonly InstallationService $installationService,
        private readonly CertificateFreeradiusInfoService $certificateFreeradiusInfoService,
        private readonly CertificateRadsecproxyInfoService $certificateRadsecproxyInfoService,
    ) {
    }

    #[Route('/dashboard/settings/certificatesManagement', name: 'admin_dashboard_settings_certs_management')]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsCertificatesManagement(): Response
    {
        $lastCompletedInstallation = $this->installationProgressRepository->getLastCompleted();
        $lastCompletedCertificate = $this->certificateSetupProcessRepository->getLatestCompletedProcess();
        if ($lastCompletedInstallation instanceof InstallationProgress) {
            $installationDate = $lastCompletedInstallation->getUpdatedAt();
        }
        if ($lastCompletedCertificate instanceof CertificateSetupProcess) {
            $certificateDate = $lastCompletedCertificate->getUpdatedAt();
        }

        $processState = $this->certificateProcessCheckerService->getProcessState(true);
        $process = $processState['process'] ?? null;

        $certificateSetRadsecproxy = $this->certificateRadsecproxyInfoService->getLatestCertificatesSet($process);

        $certificateSetFreeradius = $this->certificateFreeradiusInfoService->getLatestCertificatesSet($process);

        $certificateSet = array_merge($certificateSetRadsecproxy, $certificateSetFreeradius);



      // Default render
        return $this->render('dashboard/shared/settings_actions.html.twig', [
        'data' => $this->getSettings->getSettings(),
        'lastCompletedInstallation' => $installationDate ?? null,
        'certificateSet' => $certificateSet,
        'lastCompletedCertificates' => $certificateDate ?? null,
        ]);
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/selection',
        name: 'admin_dashboard_settings_certs_management_freeradius_selection'
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsCertificatesManagementSelection(): Response
    {
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

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/certs_selection.html.twig',
            [
            'data' => $this->getSettings->getSettings(),
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/certificates/abort',
        name: 'admin_dashboard_settings_certs_management_certificates_abort',
        methods: ['POST']
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsCertificatesManagementCertificatesAbort(
        Request $request
    ): Response {
      /** @var User $user */
        $user = $this->getUser();
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
        $process->setStatus(ProcessStatusType::ABORTED);
        $process->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->persist($process);
        $this->entityManager->flush();

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_ABORTED->value,
            new DateTime(),
            [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'by' => $user->getUuid(),
            ]
        );

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
        '/dashboard/settings/certificatesManagement/systemReset',
        name: 'admin_dashboard_settings_certs_management_system_reset',
        methods: ['POST']
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsCertificatesManagementSystemReset(Request $request): Response
    {
      /** @var User $user */
        $user = $this->getUser();

      // Abort pending Installation process if exists
        $installationProcess = $this->installationProgressRepository->getLast();
        if (
            $installationProcess &&
            $installationProcess->getInstallationState() !== ProcessStatusType::COMPLETED
        ) {
            $installationProcess->setInstallationState(ProcessStatusType::ABORTED);
            $installationProcess->setUpdatedAt(new DateTime());
            $this->entityManager->persist($installationProcess);

          // Reset system to last valid installation config
            $this->installationService->resetToLastInstallation();
        }

      // Abort pending Certificate process if exists
        $certificateProcess = $this->certificateProcessCheckerService->getCurrentProcess();
        if ($certificateProcess instanceof \App\Entity\CertificateSetupProcess) {
            $certificateProcess->setStatus(ProcessStatusType::ABORTED);
            $certificateProcess->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($certificateProcess);
        }

        $this->entityManager->flush();

      // Set session to redirect the user
        $session = $request->getSession();
        $session->set(SessionStatus::SYSTEM_RESET_REQUEST->value, 'admin_dashboard_settings_certs_installation');

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::SYSTEM_RESET_REQUEST_STARTED->value,
            new DateTime(),
            [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'by' => $user->getUuid(),
            ]
        );

        $this->addFlash(
            'success',
            $this->translator->trans(
                'systemResetRequestStarted',
                [],
                'controllers'
            )
        );

        return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
    }
}
