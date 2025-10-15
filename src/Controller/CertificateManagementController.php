<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AdminConfigDTO;
use App\DTO\CertificateRadSecUploadDTO;
use App\DTO\DbSetupDTO;
use App\DTO\JwtDTO;
use App\DTO\SettingsDTO;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\DataBaseSetupType;
use App\Enum\FirewallType;
use App\Enum\SettingsConfigType;
use App\Form\AdminConfigType;
use App\Form\CertificateUploadType;
use App\Form\DbSetupType;
use App\Form\JwtType;
use App\Form\SettingsType;
use App\Service\CertificateCommandsService;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateStorageService;
use App\Service\DatabaseConnectionService;
use App\Service\GetSettings;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelInterface;
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
        private readonly CertificateStorageService $certificateStorageService,
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificateCommandsService $certificateCommandsService,
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

    #[Route('/dashboard/settings/certificatesManagement/certificates/radsecproxy',
        name: 'admin_dashboard_settings_certs_radsecproxy')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementRadsecproxy(
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();

        // Check current certificateProcess status
        $processState = $this->certificateProcessCheckerService->getProcessState();

        // Return the user to the correct step
        if ($processState['active'] && $processState['nextRoute'] !== 'admin_dashboard_settings_certs_radsecproxy') {
            return $this->redirectToRoute($processState['nextRoute']);
        }

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

            // After the files are validated and the processed, update them once again to add the radsecProxyFormCompletionAt
            $process->setRadsecproxyFormCompletedAt(new DateTimeImmutable());
            $process->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            /* TODO'S
                1 - Make the new entity to be related with the "Certificate" one -> done
                   1.1 - Rework the services for the connection -> done
                2 - return the command to execute on the radsecproxy container
                3 - make the logic to check this process has been finished -> done check this certificateProcessCheckerService
            */
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

    #[Route('/dashboard/settings/certificatesManagement/certificates/radsecproxy/config',
        name: 'admin_dashboard_settings_certs_radsecproxy_config')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementRadsecproxyConfig(): Response
    {
        $data = $this->getSettings->getSettings();

        // Check current process
        $processState = $this->certificateProcessCheckerService->getProcessState();

        if (!$processState['active'] || $processState['stage'] !== 'radsecproxy_config_commands') {
            return $this->redirectToRoute('admin_dashboard_settings_certs_management');
        }

        // Get the ready-to-execute commands
        $commands = $this->certificateCommandsService->getRadsecproxyRenewCommands();

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/radsecproxy/config.html.twig',
            [
                'data' => $data,
                'commands' => $commands,
                'processState' => $processState,
            ]
        );
    }

    #[Route('/dashboard/settings/certificatesManagement/freeradius',
        name: 'admin_dashboard_settings_certs_freeradius'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementFreeradius(): Response
    {
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
            'potato' => 'potato'
        ]);
    }
}
