<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CertificateRadSecUploadDTO;
use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateProcessStatus;
use App\Enum\CertificateTestResult;
use App\Enum\FirewallType;
use App\Form\CertificateUploadType;
use App\Form\SimpleSubmitFormType;
use App\Repository\CertificateRepository;
use App\Service\CertificateRadsecproxyCommandsService;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateStorageService;
use App\Service\GetSettings;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class CertificateManagementController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly CertificateStorageService $certificateStorageService,
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificateRadsecproxyCommandsService $certificateRadsecproxyCommandsService,
        private readonly CertificateRepository $certificateRepository,
    ) {
    }

    #[Route('/dashboard/settings/certificatesManagement', name: 'admin_dashboard_settings_certs_management')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagement(): Response
    {
        // TODO - Make the same verifications of the process steps for the installation here
        // Check current certificateProcess status
        $processState = $this->certificateProcessCheckerService->getProcessState();

        // If there's an active process, redirect depending on its current stage
        if ($processState['active']) {
            $stages = $processState['stages'];

            // If upload completed but config not yet applied
            if (($stages['radsecproxy_upload'] ?? false) && !($stages['radsecproxy_config'] ?? false)) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_config');
            }

            // If config applied but test not completed
            if (($stages['radsecproxy_config'] ?? false) && !($stages['radsecproxy_test'] ?? false)) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_test');
            }
        }

        $data = $this->getSettings->getSettings();

        // Default render
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
        // TODO When a process is incompleted the page should ALERT the user always about this miss configured action
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
        // Get current process state
        $processState = $this->certificateProcessCheckerService->getProcessState();

        // If there's no active process
        if ($processState['active']) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'pendingActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                )
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_config');
        }

        $data = $this->getSettings->getSettings();

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
                    $process
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
        $commands = $this->certificateRadsecproxyCommandsService->getRenewCommands();

        // Form handling
        $form = $this->createForm(SimpleSubmitFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($process?->getRadsecproxyConfigAppliedAt() instanceof DateTimeImmutable) {
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

                // Redirect to the next stage automatically
                return $this->redirectToRoute(
                    'admin_dashboard_settings_certs_radsecproxy_test',
                );
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
            'dashboard/shared/settings_actions/certificatesManagement/certificates/radsecproxy/test.html.twig',
            [
                'data' => $data,
                'processState' => $processState,
                'process' => $processState['process'],
            ]
        );
    }

    /**
     * @throws \JsonException
     */
    #[Route(
        '/dashboard/settings/certificatesManagement/radsecproxy/test/run',
        name: 'admin_dashboard_settings_certs_radsecproxy_test_run',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function runRadsecproxyTest(Request $request): JsonResponse
    {
        $processEntity = $this->certificateProcessCheckerService->getCurrentProcess();

        // Ensure an active process exists
        if (!$processEntity instanceof CertificateSetupProcess) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $this->translator->trans(
                    'noActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Decode request payload
        $payload = json_decode(
            $request->getContent() ?: '{}',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $remoteHost = $payload['remote_host'] ?? $processEntity->getRemoteHost();
        $remotePort = isset($payload['remote_port']) ? (int)$payload['remote_port'] : 2083;

        if (!$remoteHost) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $this->translator->trans(
                    'missingRequiredForRadsecProxyTest',
                    [],
                    'controllers'
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Everytime the user tries a new test it will save the used credentials
        $processEntity->setRemoteHost($remoteHost);
        $processEntity->setRemotePort($remotePort);
        $processEntity->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->persist($processEntity);
        $this->entityManager->flush();
        $cafile = $this->getParameter('kernel.project_dir') . '/config/wba_chain/ca-bundle-wba.pem';

        try {
            // Check CA bundle file exists
            if (!file_exists($cafile)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'CA bundle file not found',
                    'path' => realpath($cafile),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Build full paths
            $clientCert = $this->certificateRepository->findLatestByProcessAndName(
                $processEntity,
                'clientRADSECPROXY' // This name comes from the DTO Upload Radsecproxy
            );
            $keyCert = $this->certificateRepository->findLatestByProcessAndName(
                $processEntity,
                'keyRADSECPROXY' // Same for this one
            );

            $basePath = $this->getParameter('kernel.project_dir') . '/var/certs/';
            $clientCertPath = $clientCert ? $basePath . $clientCert->getFilePath() : null;
            $keyCertPath = $keyCert ? $basePath . $keyCert->getFilePath() : null;

            // Validate certificate files exist
            if (!$clientCertPath || !$keyCertPath || !file_exists($clientCertPath) || !file_exists($keyCertPath)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Client or key certificate file not found',
                    'clientCertPath' => $clientCertPath,
                    'keyCertPath' => $keyCertPath,
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => true,
                    'cafile' => $cafile,
                    'local_cert' => $clientCertPath,
                    'local_pk' => $keyCertPath,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                ]
            ]);

            // Open the TLS connection
            $connection = @stream_socket_client(
                "tls://{$remoteHost}:{$remotePort}",
                $errno,
                $errstr,
                15,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($connection) {
                // THIS IS OK [0] -> a non-false $connection means the TLS handshake succeeded.
                // The value 0 returned in some logs (like OpenSSL verify return code) is success, not failure.
                // Meaning the condition is inverted
                // Update DB when test fails
                $this->certificateRadsecproxyCommandsService->updateRadsecproxyTestResult(
                    $processEntity,
                    CertificateTestResult::FAILED
                );

                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'TLS Handshake Failed',
                    'messageDetails' => "Failed to create socket: [$errno] $errstr",
                    'details' => [
                        'host' => $remoteHost,
                        'port' => $remotePort,
                        'error' => $errstr,
                        'code' => $errno,
                        'basePath' => $basePath,
                        'cafile' => $cafile,
                        'clientCertPath' => $clientCertPath,
                        'keyCertPath' => $keyCertPath,
                    ],
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            // Only close if it’s a valid resource
            if (is_resource($connection)) {
                fclose($connection);
            }

            // Update DB when test passes
            $this->certificateRadsecproxyCommandsService->updateRadsecproxyTestResult(
                $processEntity,
                CertificateTestResult::PASSED
            );

            return new JsonResponse([
                'status' => 'success',
                'message' => 'TLS handshake OK using WBA CA bundle',
            ]);
        } catch (Throwable $e) {
            // Update DB when test fails
            $this->certificateRadsecproxyCommandsService->updateRadsecproxyTestResult(
                $processEntity,
                CertificateTestResult::FAILED
            );

            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
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

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
        ]);
    }
}
