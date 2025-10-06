<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\DbSetupDTO;
use App\Form\DbSetupType;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CertificateManagementController extends AbstractController
{

    public function __construct(
        private readonly GetSettings $getSettings,
    ) {
    }

    #[Route('/dashboard/settings/certificatesManagement', name: 'admin_dashboard_settings_certs_management')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagement(
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/dashboard/settings/certificatesManagement/certificates', name: 'admin_dashboard_settings_certs')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementCertificates(
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();

        /* TODO
            1 - MAKE THE NEW ENTITY RESPONSIBLE TO THIS progress saving
            2 - FIND A WAY TO SUBMIT THIS 2 FILES WITH FILE SYSTEM (client.pem and key.pem) about this -> wba certs fpr /root/wba-openroaming-connector/hybrid/radsecproxy/certs (CONFIRM path) -> https://github.com/wireless-broadband-alliance/wba-openroaming-connector
            3 - GET THE DATA CONTENT OF EACH FILE
        */

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/dashboard/settings/certificatesManagement/installation', name: 'admin_dashboard_settings_certs_installation')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallation(
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();

        $dbDTO = new DbSetupDTO();

        $form = $this->createForm(DbSetupType::class, $dbDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dd('estou a chegar aqui');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/dataBase.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $dbDTO
            ]
        );
    }
}
