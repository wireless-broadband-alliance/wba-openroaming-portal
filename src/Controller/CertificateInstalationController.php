<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CertificateInstalationController extends AbstractController
{

    public function __construct(
        private readonly GetSettings $getSettings,
    ) {
    }
    #[Route('/dashboard/settings/certificatesInstallation', name: 'admin_dashboard_settings_certs')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsSMS(
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
        ]);
    }
}
