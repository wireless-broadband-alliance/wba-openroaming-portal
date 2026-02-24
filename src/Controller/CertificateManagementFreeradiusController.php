<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CertificateFreeradiusUploadManualDTO;
use App\DTO\CertificatesFreeradiusPasteDTO;
use App\DTO\CloudflareDTO;
use App\Entity\CertificateSetupProcess;
use App\Entity\Setting;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateTestResult;
use App\Enum\FirewallType;
use App\Enum\ProcessStatusType;
use App\Enum\SessionStatus;
use App\Enum\SettingName;
use App\Exception\FreeradiusTestException;
use App\Form\CertificateFreeradiusUploadManualType;
use App\Form\CertificatesFreeradiusPasteType;
use App\Form\CloudflareType;
use App\Form\SimpleSubmitFormType;
use App\Repository\SettingRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\CertificateCheckerService;
use App\Service\CertificateFreeradiusCommandsService;
use App\Service\CertificateFreeradiusGenerator;
use App\Service\CertificateFreeradiusHTTPChallengeCommandsService;
use App\Service\CertificateFreeradiusInfoService;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateStorageService;
use App\Service\CertificateWriterUpdateService;
use App\Service\CloudflareService;
use App\Service\EventActions;
use App\Service\FreeradiusCertificateValidatorService;
use App\Service\FreeradiusTestOrchestrator;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class CertificateManagementFreeradiusController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService,
        private readonly CertificateStorageService $certificateStorageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificateFreeradiusInfoService $certificateFreeradiusInfoService,
        private readonly CertificateFreeradiusCommandsService $certificateFreeradiusCommandsService,
        private readonly CertificateWriterUpdateService $certificateWriterUpdateService,
        private readonly CertificateCheckerService $certificateCheckerService,
        private readonly EventActions $eventActions,
        private readonly CertificateFreeradiusGenerator $certificateFreeradiusGenerator,
        private readonly FreeradiusTestOrchestrator $freeradiusTestOrchestrator,
        private readonly CloudflareService $cloudflareService,
        private readonly SettingRepository $settingRepository,
        private readonly FreeradiusCertificateValidatorService $freeradiusCertificateValidatorService,
        private readonly CertificateFreeradiusHTTPChallengeCommandsService $httpChallengeCommands,
    ) {
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/upload',
        name: 'admin_dashboard_settings_certs_freeradius_upload'
    )]
    #[IsGranted(UserAuthenticationVoter::CERTIFICATES_MANAGEMENT_WRITE)]
    public function settingsCertificatesManagementFreeradiusUpload(
        Request $request
    ): Response {
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

        $data = $this->getSettings->getSettings();

        // Prepare session for freeradius stepper detection
        $session = $request->getSession();
        $session->set(
            SessionStatus::FREERADIUS_SETUP_PROCESS_TYPE->value,
            AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_UPLOAD_MANUAL->value,
        );

        // Prepare DTO
        $certificateUploadDTO = new CertificateFreeradiusUploadManualDTO();

        // Create & handle form
        $form = $this->createForm(CertificateFreeradiusUploadManualType::class, $certificateUploadDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $process = $this->certificateProcessCheckerService->getCurrentProcess();
            // In case there's not active process
            if (!$process instanceof CertificateSetupProcess) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('noActiveProcess', [], 'CertificateProcessCheckerService')
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_upload');
            }

            // Check if the uploaded cert is a EV
            if (in_array('CERTIFICATE_NOT_EV_WARNING', $certificateUploadDTO->notices, true)) {
                $process->setIsFreeradiusCertEV(false);
                $this->addFlash(
                    'warning',
                    $this->translator->trans('not_ev_warning', [], 'controllers')
                );
            } else {
                $process->setIsFreeradiusCertEV(true);
            }

            if ($certificateUploadDTO->ca instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->ca,
                    CertificateFileName::CA_PEM->value,
                    CertificateMachineType::FREERADIUS->value,
                    $process
                );
            }

            if ($certificateUploadDTO->cert instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->cert,
                    CertificateFileName::CERT_PEM->value,
                    CertificateMachineType::FREERADIUS->value,
                    $process
                );
            }

            if ($certificateUploadDTO->chain instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->chain,
                    CertificateFileName::CHAIN_PEM->value,
                    CertificateMachineType::FREERADIUS->value,
                    $process
                );
            }

            if ($certificateUploadDTO->fullChain instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->fullChain,
                    CertificateFileName::FULL_CHAIN_PEM->value,
                    CertificateMachineType::FREERADIUS->value,
                    $process
                );
            }

            if ($certificateUploadDTO->privKey instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $certificateUploadDTO->privKey,
                    CertificateFileName::PRIVATE_KEY_PEM->value,
                    CertificateMachineType::FREERADIUS->value,
                    $process,
                    true
                );
            }

            // After the files are validated and the processed, update them once again to add
            $process->setFreeradiusFormCompletedAt(new DateTimeImmutable());
            $process->setFreeradiusConfigAppliedAt(null);
            $process->setFreeradiusTestResult(null);
            $process->setIsFreeradiusCloudflare(false);
            $process->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            /** @var User $user */
            $user = $this->getUser();
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_UPLOAD_MANUAL->value,
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
                    'freeradiusCertUploadedSuccessfully',
                    [],
                    'controllers'
                )
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_freeradius_config');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/upload.html.twig',
            [
                'data' => $data,
                'certificateUploadDTO' => $certificateUploadDTO,
                'form' => $form->createView(),
                'context' => FirewallType::DASHBOARD->value,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/config',
        name: 'admin_dashboard_settings_certs_freeradius_config'
    )]
    #[IsGranted(UserAuthenticationVoter::CERTIFICATES_MANAGEMENT_WRITE)]
    public function settingsCertificatesManagementFreeradiusConfig(
        Request $request
    ): Response {
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
        $certificateSet = $this->certificateFreeradiusInfoService
            ->getLatestCertificatesSet($process);

        /** @var array<string, array{content?: string|null}> $renewCertificateSet */
        $renewCertificateSet = [];

        foreach ($certificateSet as $key => $certificate) {
            $content = $certificate['content'] ?? null;

            $renewCertificateSet[$key] = [
                'content' => is_string($content) ? $content : null,
            ];
        }

        $commands = $this->certificateFreeradiusCommandsService
            ->getRenewCommands($renewCertificateSet);

        // Form handling
        $form = $this->createForm(SimpleSubmitFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($process?->getFreeradiusConfigAppliedAt() instanceof DateTimeImmutable) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('configAlreadyApplied', [], 'controllers')
                );
            } elseif ($form->isValid()) {
                $process = $this->certificateProcessCheckerService->getCurrentProcess();
                // Update local certificates in "signing-keys"
                /** @var array<string, array{content: string}> $writeCertificateSet */
                $writeCertificateSet = [];

                foreach ($certificateSet as $key => $certificate) {
                    $content = $certificate['content'] ?? null;

                    if (!is_string($content)) {
                        continue;
                    }

                    $writeCertificateSet[$key] = [
                        'content' => $content,
                    ];
                }

                $this->certificateWriterUpdateService
                    ->writeCertificates($writeCertificateSet);

                // Check if the uploaded cert is a EV
                $certContent = $certificateSet['certFREERADIUS']['content'] ?? null;

                if ($certContent !== null) {
                    $isEv = $this->certificateFreeradiusInfoService->isEvCertificate($certContent);

                    // If the uploaded certificates are EV's it should generate the PfxSigningKey for windows profiles
                    if ($isEv) {
                        $scriptPath = $this->getParameter('kernel.project_dir') . '/tools/generatePfxSigningKey.sh';
                        $generatePFX = new Process(['/bin/sh', $scriptPath]);
                        $generatePFX->setTimeout(30);

                        try {
                            $generatePFX->run();

                            if (!$generatePFX->isSuccessful()) {
                                throw new ProcessFailedException($generatePFX);
                            }

                            $this->addFlash(
                                'success',
                                $this->translator->trans('pfx.success', [], 'controllers')
                            );
                        } catch (Throwable) {
                            $this->addFlash(
                                'error',
                                $this->translator->trans('pfx.failure', [], 'controllers')
                            );

                            // Redirect to the next stage automatically
                            return $this->redirectToRoute(
                                'admin_dashboard_settings_certs_freeradius_upload',
                            );
                        }
                    }
                }

                // Update the settings table with the new certs content
                $caContentParsed = $this->certificateCheckerService->parseCertificate(
                    $certificateSet[CertificateFileName::CA_PEM->value]['content']
                );
                $certContentParsed = $this->certificateCheckerService->parseCertificate(
                    $certificateSet[CertificateFileName::CERT_PEM->value]['content']
                );
                $caParsed = $caContentParsed;
                $fingerprint = $caParsed['fingerprintSHA1'];

                if (!is_string($fingerprint)) {
                    unset($caParsed['fingerprintSHA1']);
                } else {
                    $caParsed['fingerprintSHA1'] = $fingerprint;
                }

                $certParsed = $certContentParsed;
                $fingerprint = $certParsed['fingerprintSHA1'];

                if (!is_string($fingerprint)) {
                    unset($certParsed['fingerprintSHA1']);
                } else {
                    $certParsed['fingerprintSHA1'] = $fingerprint;
                }

                $this->certificateWriterUpdateService->updateFromParsedCertificates(
                    $caParsed,
                    $certParsed
                );

                $process->setFreeradiusConfigAppliedAt(new DateTimeImmutable());
                $process->setFreeradiusDomainName($certParsed['subject']['CN']);
                $process->setUpdatedAt(new DateTimeImmutable());

                $this->entityManager->persist($process);
                $this->entityManager->flush();

                /** @var User $user */
                $user = $this->getUser();
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_CONFIG->value,
                    new DateTime(),
                    [
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent'),
                        'by' => $user->getUuid(),
                    ]
                );

                $this->addFlash(
                    'success',
                    $this->translator->trans('freeradiusConfigAppliedSuccessfully', [], 'controllers')
                );

                // Redirect to the next stage automatically
                return $this->redirectToRoute(
                    'admin_dashboard_settings_certs_freeradius_test',
                );
            }
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/config.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'processState' => $processState,
                'certificateSet' => $certificateSet,
                'commands' => $commands,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/test',
        name: 'admin_dashboard_settings_certs_freeradius_test',
        defaults: ['mode' => 'freeradius']
    )]
    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/cloudflare/httpChallenge',
        name: 'admin_dashboard_settings_certs_freeradius_cloudflare_httpChallenge',
        defaults: ['mode' => 'http_challenge']
    )]
    #[IsGranted(UserAuthenticationVoter::CERTIFICATES_MANAGEMENT_WRITE)]
    public function settingsCertificatesManagementFreeradiusTest(
        Request $request,
        string $mode
    ): Response {
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

        $processEntity = $this->certificateProcessCheckerService->getCurrentProcess();

        // Ensure an active process exists
        if (!$processEntity instanceof CertificateSetupProcess) {
            throw new RuntimeException(
                $this->translator->trans(
                    'noActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                )
            );
        }

        // Save the event
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Fetch settings/data needed for the page
        $data = $this->getSettings->getSettings();

        // Prepare DTO
        $certificatesFreeradiusPasteDTO = new CertificatesFreeradiusPasteDTO();
        $form = $this->createForm(CertificatesFreeradiusPasteType::class, $certificatesFreeradiusPasteDTO);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Everytime the user tries a new test it will save the used credentials
            $processEntity->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($processEntity);
            $this->entityManager->flush();

            if ($mode === 'http_challenge') {
                // Extract PEM blocks
                $pastedCertificates = $certificatesFreeradiusPasteDTO->certificates;
                $rawExtracted = $this->freeradiusCertificateValidatorService
                    ->extractCertificates($pastedCertificates);

                // Map to known identifiers
                $map = [
                    CertificateFileName::CA_PEM->value,
                    CertificateFileName::CERT_PEM->value,
                    CertificateFileName::CHAIN_PEM->value,
                    CertificateFileName::FULL_CHAIN_PEM->value,
                    CertificateFileName::PRIVATE_KEY_PEM->value,
                ];
                $extractCertificates = [];

                foreach ($map as $index => $key) {
                    if (!isset($rawExtracted[$index])) {
                        continue;
                    }

                    $extractCertificates[$key] = trim($rawExtracted[$index]);
                }

                if (
                    !isset(
                        $extractCertificates[CertificateFileName::CA_PEM->value],
                        $extractCertificates[CertificateFileName::CERT_PEM->value],
                    )
                ) {
                    throw new RuntimeException('Missing required certificates');
                }

                // Parse certificates (for metadata)
                $caParsed = $this->certificateCheckerService
                    ->parseCertificate($extractCertificates[CertificateFileName::CA_PEM->value]);

                $certParsed = $this->certificateCheckerService
                    ->parseCertificate($extractCertificates[CertificateFileName::CERT_PEM->value]);

                if (!is_string($caParsed['fingerprintSHA1'])) {
                    unset($caParsed['fingerprintSHA1']);
                }

                if (!is_string($certParsed['fingerprintSHA1'])) {
                    unset($certParsed['fingerprintSHA1']);
                }

                /**
                 * Convert PEM strings → UploadedFile objects to be saved on the tmp
                 *
                 */
                foreach ($extractCertificates as $type => $pemContent) {
                    // Creates fake temporally destination folder for VichUpload
                    $tmpPath = tempnam(sys_get_temp_dir(), 'freeradius_');

                    if ($tmpPath === false) {
                        throw new RuntimeException('Unable to create temporary file');
                    }

                    file_put_contents($tmpPath, $pemContent);

                    $uploadedFile = new UploadedFile(
                        $tmpPath,
                        $type . '.pem',
                        'application/x-pem-file',
                        null,
                        true // mark as already moved
                    );

                    // Store them
                    $this->certificateStorageService->storeUploadedFile(
                        $uploadedFile,
                        $type,
                        CertificateMachineType::FREERADIUS->value,
                        $processEntity,
                        true
                    );
                }

                // Update process entity
                $processEntity->setFreeradiusFormCompletedAt(new DateTimeImmutable());
                $processEntity->setFreeradiusConfigAppliedAt(new DateTimeImmutable());
                $processEntity->setIsFreeradiusCertEV(false);
                $processEntity->setIsFreeradiusCloudflare(true);
                $processEntity->setFreeradiusTestResult(CertificateTestResult::PASSED);
                $processEntity->setFreeradiusDomainName($certParsed['subject']['CN'] ?? null);
                $processEntity->setUpdatedAt(new DateTimeImmutable());

                $this->entityManager->flush();

                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_UPLOAD_CLOUDFLARE_HTTP_CHALLENGE->value,
                    new DateTime(),
                    [
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent'),
                        'by' => $currentUser->getUuid(),
                    ]
                );

                $this->addFlash(
                    'success',
                    $this->translator->trans('httpChallengeAppliedSuccessfully', [], 'controllers')
                );
            } else {
                // Validate all exist
                $basePath = $this->getParameter('kernel.project_dir') . '/signing-keys/';
                $paths = [
                    CertificateFileName::CA_PEM->value =>
                        $basePath . 'ca/' . CertificateFileName::CA_PEM_FILE->value,
                    CertificateFileName::CERT_PEM->value =>
                        $basePath . CertificateFileName::CERT_PEM_FILE->value,
                    CertificateFileName::CHAIN_PEM->value =>
                        $basePath . CertificateFileName::CHAIN_PEM_FILE->value,
                    CertificateFileName::FULL_CHAIN_PEM->value =>
                        $basePath . CertificateFileName::FULL_CHAIN_PEM_FILE->value,
                    CertificateFileName::PRIVATE_KEY_PEM->value =>
                        $basePath . CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
                ];

                $missing = array_filter($paths, static fn($path) => !file_exists($path));

                if ($missing !== []) {
                    $missingFiles = implode(', ', array_keys($missing));
                    throw new RuntimeException(
                        sprintf(
                            'Missing certificate files: %s',
                            $missingFiles
                        )
                    );
                }

                try {
                    // Calls the Orchestrator to run the test
                    $this->freeradiusTestOrchestrator->run(
                        $request,
                        $processEntity,
                        $paths,
                        $certificatesFreeradiusPasteDTO->certificates
                    );

                    // Flash success with translation
                    $this->addFlash(
                        'success',
                        $this->translator->trans(
                            'freeradiusTestPassed',
                            [
                                '%caBundle%' => 'WBA CA bundle',
                            ],
                            'controllers'
                        )
                    );
                } catch (FreeradiusTestException $exception) {
                    // Set the process as failed
                    $processEntity->setStatus(ProcessStatusType::IN_PROGRESS);
                    $processEntity->setFreeradiusTestResult(CertificateTestResult::FAILED);
                    $this->entityManager->persist($processEntity);
                    $this->entityManager->flush();

                    // Flash translated exception message
                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            $exception->getMessage(),
                            $exception->getContext(),
                            'FreeradiusTestException'
                        )
                    );
                }
            }
        }

        $formFinishProcess = $this->createForm(SimpleSubmitFormType::class);
        $formFinishProcess->handleRequest($request);
        if ($formFinishProcess->isSubmitted() && $formFinishProcess->isValid()) {
            $session = $request->getSession();
            $session->remove(SessionStatus::SYSTEM_RESET_REQUEST->value);
            $processEntity->setStatus(ProcessStatusType::COMPLETED);
            $this->entityManager->persist($processEntity);
            $this->entityManager->flush();

            if ($mode === 'http_challenge' &&
                $processEntity->getFreeradiusTestResult() === CertificateTestResult::PASSED
            ) {
                // Load the latest FREERADIUS certificates for this process
                $latestCerts = $this->certificateFreeradiusInfoService->getLatestCertificatesSet($processEntity);
                if ($latestCerts === []) {
                    throw new RuntimeException('No FREERADIUS certificates found for this process.');
                }

                // Prepare the certificate set to write
                $certificateSet = [];
                $caParsed = $certParsed = null;

                foreach ($latestCerts as $type => $certInfo) {
                    $content = $certInfo['content'] ?? null;

                    // Skip null/false content
                    if (!is_string($content)) {
                        continue;
                    }

                    $certificateSet[$type] = ['content' => $content];

                    // Parse CA and CERT for metadata
                    if ($type === CertificateFileName::CA_PEM->value) {
                        $caParsed = $this->certificateCheckerService->parseCertificate($content);
                    } elseif ($type === CertificateFileName::CERT_PEM->value) {
                        $certParsed = $this->certificateCheckerService->parseCertificate($content);
                    }
                }

                // Write to signing-keys folder
                $this->certificateWriterUpdateService->writeCertificates($certificateSet);

                // Update database/settings with parsed certificates
                // Remove invalid fingerprintSHA1 for CA && CERT
                // Ensure fingerprintSHA1 is strictly a string
                $normalizedCaParsed = [];
                $normalizedCertParsed = [];
                if (isset($caParsed['fingerprintSHA1']) && is_string($caParsed['fingerprintSHA1'])) {
                    $normalizedCaParsed['fingerprintSHA1'] = $caParsed['fingerprintSHA1'];
                }
                if (isset($certParsed['fingerprintSHA1']) && is_string($certParsed['fingerprintSHA1'])) {
                    $normalizedCertParsed['fingerprintSHA1'] = $certParsed['fingerprintSHA1'];
                }

                if ($normalizedCaParsed && $normalizedCertParsed) {
                    $this->certificateWriterUpdateService
                        ->updateFromParsedCertificates($normalizedCaParsed, $certParsed);
                }
            } else {
                // Update process entity
                $processEntity->setIsFreeradiusCertEV(false);
                $processEntity->setIsFreeradiusCloudflare(true);
                // Mark as PASSED and finish configuration
                $processEntity->setFreeradiusTestResult(CertificateTestResult::PASSED);
                $processEntity->setUpdatedAt(new DateTimeImmutable());
            }

            // Redirect to the next stage automatically
            return $this->redirectToRoute('admin_dashboard_settings_certs_management');
        }

        $domain = $request->getHost();
        $httpChallengeCommands = $this->httpChallengeCommands->getCommands(
            $domain,
            $currentUser->getEmail()
        );

        $template = match ($mode) {
            'http_challenge' =>
                'dashboard/shared/settings_actions/'
                . 'certificatesManagement/certificates/'
                . 'freeradius/httpChallenge/http_challenge.html.twig',

            default =>
                'dashboard/shared/settings_actions/'
                . 'certificatesManagement/certificates/'
                . 'freeradius/test.html.twig',
        };

        $allowSkipProcess = $this->certificateCheckerService->verifyCertificates();

        return $this->render(
            $template,
            [
                'certificatesFreeradiusPasteDTO' => $certificatesFreeradiusPasteDTO,
                'data' => $data,
                'processState' => $processState,
                'process' => $processState['process'],
                'form' => $form->createView(),
                'formFinishProcess' => $formFinishProcess->createView(),
                'mode' => $mode,
                'commands' => $httpChallengeCommands,
                'allowSkipProcess' => $allowSkipProcess,
            ]
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface|RandomException
     */
    #[
        Route(
            '/dashboard/settings/certificatesManagement/freeradius/cloudflare/dnsChallenge',
            name: 'admin_dashboard_settings_certs_freeradius_cloudflare_dnsChallenge',
        )]
    #[IsGranted(UserAuthenticationVoter::CERTIFICATES_MANAGEMENT_WRITE)]
    public function cloudflareDNSChallenge(Request $request): Response
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

        $data = $this->getSettings->getSettings();

        // Prepare session for freeradius stepper detection
        $session = $request->getSession();
        $session->set(
            SessionStatus::FREERADIUS_SETUP_PROCESS_TYPE->value,
            AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_UPLOAD_CLOUDFLARE_DNS_CHALLENGE->value,
        );

        $dto = new CloudflareDTO();
        $form = $this->createForm(CloudflareType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $process = $this->certificateProcessCheckerService->getCurrentProcess();
            // In case there's not active process
            if (!$process instanceof CertificateSetupProcess) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('noActiveProcess', [], 'CertificateProcessCheckerService')
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_radsecproxy_upload');
            }

            if (!$this->cloudflareService->validate($dto)) {
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'cloudflareTokenNotMatchesHost',
                        ['%host%' => $dto->host],
                        'controllers'
                    )
                );
                return $this->redirectToRoute(
                    'admin_dashboard_settings_certs_freeradius_cloudflare_dnsChallenge'
                );
            }

            /** @var User $user */
            $user = $this->getUser();

            $this->certificateFreeradiusGenerator->generateCertificatesWithCloudflareDns(
                $dto->host,
                $user,
                $dto->token
            );

            $certificateSetupProcess = $this->certificateProcessCheckerService->getCurrentProcess();

            if ($certificateSetupProcess instanceof CertificateSetupProcess) {
                $certificateSetupProcess->setFreeradiusDomainName($dto->host);
                $certificateSetupProcess->setFreeradiusFormCompletedAt(new DateTimeImmutable());
                $certificateSetupProcess->setFreeradiusConfigAppliedAt(null);
                $certificateSetupProcess->setIsFreeradiusCloudflare(true);
                $setting = $this->settingRepository->findOneBy(['name' => SettingName::CLOUDFLARE_TOKEN->value]);
                if ($setting) {
                    $setting->setValue($dto->token);
                } else {
                    $setting = new Setting();
                    $setting->setName(SettingName::CLOUDFLARE_TOKEN->value);
                    $setting->setValue($dto->token);
                    $this->entityManager->persist($setting);
                }
                $this->entityManager->persist($certificateSetupProcess);
                $this->entityManager->persist($setting);
                $this->entityManager->flush();
            }

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType
                ::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_UPLOAD_CLOUDFLARE_DNS_CHALLENGE->value,
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
                    'freeradiusCertUploadedSuccessfully',
                    [],
                    'controllers'
                )
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_freeradius_config');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/' .
            'certificates/freeradius/dnsChallenge/dns_challenge.html.twig',
            [
                'data' => $data,
                'cloudflareDTO' => $dto,
                'form' => $form->createView(),
                'context' => FirewallType::DASHBOARD->value,
                'processState' => $processState,
                'process' => $processState['process'],
            ]
        );
    }
}
