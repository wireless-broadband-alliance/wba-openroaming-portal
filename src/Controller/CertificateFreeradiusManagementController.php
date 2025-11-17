<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\FirewallType;
use App\Service\CertificateProcessCheckerService;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius',
        name: 'admin_dashboard_settings_certs_freeradius'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementFreeradius(): Response
    {
        // Get current process state
        $processState = $this->certificateProcessCheckerService->getProcessState();

        // If no active process, redirect to the first stage
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

        // If there's active process, redirect to the config stage
        if ($processState['stages']['radsecproxy_test'] === false) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'blockAccessUntilRadsecproxyTestPassed',
                    [],
                    'CertificateProcessCheckerService'
                )
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_config');
        }

        $data = $this->getSettings->getSettings();

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/upload.html.twig',
            [
                'data' => $data,
                'context' => FirewallType::DASHBOARD->value,
            ]
        );
    }
}
