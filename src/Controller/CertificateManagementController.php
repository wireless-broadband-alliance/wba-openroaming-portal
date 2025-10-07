<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CertificateUploadDTO;
use App\DTO\DbSetupDTO;
use App\Enum\DataBaseSetupType;
use App\Enum\FirewallType;
use App\Form\CertificateUploadType;
use App\Form\DbSetupType;
use App\Service\DatabaseConnectionService;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use Symfony\Contracts\Translation\TranslatorInterface;

use function PHPUnit\Framework\isEmpty;

class CertificateManagementController extends AbstractController
{

    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly DatabaseConnectionService $databaseConnectionService,
        private readonly TranslatorInterface $translator,
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

        // Prepare DTO
        $certificateUploadDTO = new CertificateUploadDTO();

        // Create & handle form
        $form = $this->createForm(CertificateUploadType::class, $certificateUploadDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dd('NICE UPLOAD');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
            'certificateUploadDTO' => $certificateUploadDTO,
            'form' => $form->createView(),
            'context' => FirewallType::DASHBOARD->value,
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

            $openRoamingDb = $dbDTO->dbOpenRoaming;
            $freeradiusDb = $dbDTO->dbFreeradius;

            $orConnection = $this->databaseConnectionService->testDatabaseConnection($openRoamingDb);
            $frConnection = $this->databaseConnectionService->testDatabaseConnection($freeradiusDb);

            $connectionsFailed = array();

            if (!$orConnection) {
                $connectionsFailed[] = 'OpenRoaming';
            }
            if (!$frConnection) {
                $connectionsFailed[] = 'Freeradius';
            }

            if (!empty($connectionsFailed)) {
                $this->addFlash(
                    'error_admin',
                    $this->translator->trans(
                        'connectionFailed',
                        ['%dbConnections%' => implode(', ', $connectionsFailed)],
                        'controllers'
                    )
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
            }

            $this->databaseConnectionService->writeDatabaseUrlToEnv($openRoamingDb, DataBaseSetupType::DATABASE_URL->value);
            $this->databaseConnectionService->writeDatabaseUrlToEnv($freeradiusDb, DataBaseSetupType::DATABASE_FREERADIUS_URL->value);

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
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

    #[Route('/dashboard/settings/certificatesManagement/installation/settings', name: 'admin_dashboard_settings_certs_installation_settings')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationSettings(
        Request $request
    ): Response {

        $data = $this->getSettings->getSettings();

        $dbDTO = new DbSetupDTO();

        $form = $this->createForm(DbSetupType::class, $dbDTO);
        $form->handleRequest($request);

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
