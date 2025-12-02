<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CertificateSetupProcess;
use App\Entity\InstallationProgress;
use App\Enum\ProcessStatusType;
use App\Repository\CertificateSetupProcessRepository;
use App\Repository\InstallationProgressRepository;
use App\Service\CertificateProcessCheckerService;
use App\Service\GetSettings;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    #[Route('/dashboard/settings/certificatesManagement', name: 'admin_dashboard_settings_certs_management')]
    #[IsGranted('ROLE_ADMIN')]
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

        // Default render
        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $this->getSettings->getSettings(),
            'lastCompletedInstallation' => $installationDate ?? null,
            'lastCompletedCertificates' => $certificateDate ?? null,
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
        $process->setStatus(ProcessStatusType::ABORTED);
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

    #[Route('/dashboard/settings/certificatesManagement/systemReset',
        name: 'admin_dashboard_settings_certs_management_system_reset'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementSystemReset(): Response
    {
        dd('potato start process');
        // TODO FIND A WAY TO TRIGGER THE RESET OF ALL THE PLATFORM LIKE THE SYSTEM_RESET
        // TODO CREATE THIS SESSION_TOKEN "system_reset_request" -> rename first_reset and use it
    }
}
