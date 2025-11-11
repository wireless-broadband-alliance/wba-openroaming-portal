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
use App\Service\CertificateRadsecproxyCommandsService;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateStorageService;
use App\Service\GetSettings;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
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
    private const string RADSECPROXY_CONTAINER = 'hybrid-radsecproxy-1';

    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly CertificateStorageService $certificateStorageService,
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificateRadsecproxyCommandsService $certificateRadsecproxyCommandsService,
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
        $remotePort = isset($payload['remote_port']) ? (int)$payload['remote_port'] : 22;
        $remoteUser = $payload['remote_user'] ?? $processEntity->getRemoteUser();
        $remotePassword = $payload['remote_password'];
        $timeout = isset($payload['timeout']) ? (int)$payload['timeout'] : 5;

        if (!$remoteHost || !$remoteUser || !$remotePassword) {
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
        $processEntity->setRemoteUser($remoteUser);
        $processEntity->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->persist($processEntity);
        $this->entityManager->flush();

        try {
            // Test TCP connectivity first
            $connection = @fsockopen($remoteHost, 22, $errno, $errstr, $timeout);
            if (!$connection) {
                // Update DB when test fails
                $this->certificateRadsecproxyCommandsService->updateRadsecproxyTestResult(
                    $processEntity,
                    CertificateTestResult::FAILED
                );

                return new JsonResponse([
                    'status' => 'error',
                    'message' => $this->translator->trans(
                        'tcp_radsecproxy_test_failed',
                        [
                            '%host%' => $remoteHost,
                            '%port%' => $remotePort,
                            '%error%' => $errstr ?: 'unknown error',
                            '%code%' => $errno,
                        ],
                        'controllers'
                    ),
                    /*
                    'debug' => [
                        'host' => $remoteHost,
                        'port' => 22,
                        'timeout' => $timeout,
                        'connection' => $connection
                    ]
                    */
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }
            fclose($connection);

            $sshConnection = @ssh2_connect($remoteHost, $remotePort, [], ['timeout' => $timeout]);
            if (!$sshConnection || !@ssh2_auth_password($sshConnection, $remoteUser, $remotePassword)) {
                // Update DB when test fails
                $this->certificateRadsecproxyCommandsService->updateRadsecproxyTestResult(
                    $processEntity,
                    CertificateTestResult::FAILED
                );

                return new JsonResponse([
                    'status' => 'error',
                    'message' => $this->translator->trans(
                        'ssh_radsecproxy_auth_failed',
                        [
                            '%host%' => $remoteHost,
                            '%port%' => $remotePort,
                            '%user%' => $remoteUser,
                        ],
                        'controllers'
                    ),
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            // Check if the
            $checkContainerCommand = sprintf(
                "docker ps --filter 'name=%s' --format '{{.Names}}'",
                escapeshellarg(self::RADSECPROXY_CONTAINER)
            );

            $checkStream = @ssh2_exec($sshConnection, $checkContainerCommand);
            if (!$checkStream) {
                throw new RuntimeException("Failed to check container status for hybrid-radsecproxy-1");
            }

            stream_set_blocking($checkStream, true);
            $checkOutput = trim(stream_get_contents($checkStream));
            fclose($checkStream);

            if (empty($checkOutput) || $checkOutput !== self::RADSECPROXY_CONTAINER) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => $this->translator->trans(
                        'radsecproxy_container_not_running',
                        [
                            '%container%' => self::RADSECPROXY_CONTAINER,
                            '%host%' => $remoteHost,
                            '%port%' => $remotePort,
                        ],
                        'controllers'
                    ),
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            // Command to list the files and check for client.pem and key.pem
            $command = sprintf(
                "docker exec %s /bin/bash -c 'ls %s'",
                escapeshellarg(self::RADSECPROXY_CONTAINER),
                '/etc/radsecproxy/certs/'
            );

            $stream = @ssh2_exec($sshConnection, $command);
            if (!$stream) {
                throw new RuntimeException("Failed to execute command in container hybrid-radsecproxy-1");
            }

            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            fclose($stream);

            // Analyze output to see if files exist
            $hasClient = str_contains($output, 'client.pem');
            $hasKey = str_contains($output, 'key.pem');

            // If both files exist → passes, otherwise → fails
            if ($hasClient && $hasKey) {
                // TODO NOW GET THE CURRENT PROCESS AND CHECK IF THE CONTENTS OF THE KEY IS THE SAME OF THE ONES ON THE TMP FOLDER

                // Update DB when test passes
                $this->certificateRadsecproxyCommandsService->updateRadsecproxyTestResult(
                    $processEntity,
                    CertificateTestResult::PASSED
                );

                return new JsonResponse([
                    'status' => 'success',
                    'message' => $this->translator->trans(
                        'radsecproxy_test_passed',
                        ['%container%' => self::RADSECPROXY_CONTAINER],
                        'controllers'
                    ),
                ]);
            }

            // Update DB when test fails
            $this->certificateRadsecproxyCommandsService->updateRadsecproxyTestResult(
                $processEntity,
                CertificateTestResult::FAILED
            );

            // Construct error message specifying which file is missing
            $missingFiles = [];
            if (!$hasClient) {
                $missingFiles[] = 'client.pem';
            }
            if (!$hasKey) {
                $missingFiles[] = 'key.pem';
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => $this->translator->trans(
                    'radsecproxy_test_failed',
                    [
                        '%host%' => $remoteHost,
                        '%port%' => $remotePort,
                        '%container%' => self::RADSECPROXY_CONTAINER,
                        '%files%' => implode(', ', $missingFiles),
                    ],
                    'controllers'
                ),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
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
