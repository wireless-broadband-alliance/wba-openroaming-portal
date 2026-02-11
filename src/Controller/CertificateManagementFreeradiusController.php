<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CertificateFreeradiusDomainDTO;
use App\DTO\CertificateFreeradiusUploadManualDTO;
use App\DTO\CloudflareDTO;
use App\Entity\CertificateSetupProcess;
use App\Entity\CloudflareTokens;
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
use App\Form\CertificateFreeradiusDomainType;
use App\Form\CertificateFreeradiusUploadManualType;
use App\Form\CertificatesFreeradiusPasteType;
use App\Form\CloudflareType;
use App\Form\SimpleSubmitFormType;
use App\Repository\SettingRepository;
use App\Service\CertificateCheckerService;
use App\Service\CertificateFreeradiusCommandsService;
use App\Service\CertificateFreeradiusGenerator;
use App\Service\CertificateFreeradiusInfoService;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateStorageService;
use App\Service\CertificateWriterUpdateService;
use App\Service\CloudflareService;
use App\Service\DomainService;
use App\Service\EventActions;
use App\Service\FreeradiusTestOrchestrator;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
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
        private readonly DomainService $domainService,
        private readonly FreeradiusTestOrchestrator $freeradiusTestOrchestrator,
        private readonly CloudflareService $cloudflareService,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/upload',
        name: 'admin_dashboard_settings_certs_freeradius_upload'
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
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

            if (in_array('CERTIFICATE_LETS_ENCRYPT_WARNING', $certificateUploadDTO->notices, true)) {
                $process->setIsFreeradiusCertEV(false);
                $this->addFlash(
                    'warning',
                    $this->translator->trans('cert_uploaded_are_lets_encrypt_warning', [], 'controllers')
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
        '/dashboard/settings/certificatesManagement/freeradius/autoRenewDomain',
        name: 'admin_dashboard_settings_certs_freeradius_auto_renew_domain',
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsCertificatesManagementFreeradiusAutoRenewDomain(
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
            AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_UPLOAD_AUTO_DOMAIN->value,
        );

        // Prepare DTO
        $certificateFreeradiusDomainDTO = new CertificateFreeradiusDomainDTO();

        // Create & handle form
        $form = $this->createForm(CertificateFreeradiusDomainType::class, $certificateFreeradiusDomainDTO);
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

            $process->setFreeradiusDomainName($certificateFreeradiusDomainDTO->domain);
            $process->setFreeradiusFormCompletedAt(new DateTimeImmutable());
            $process->setFreeradiusConfigAppliedAt(null);
            $process->setFreeradiusTestResult(null);
            $process->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            /** @var User $user */
            $user = $this->getUser();
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_UPLOAD_AUTO_DOMAIN->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'by' => $user->getUuid(),
                ]
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_freeradius_auto_renew');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement
            /certificates/freeradius/auto_renew_domain.html.twig',
            [
                'data' => $data,
                'certificateFreeradiusDomainDTO' => $certificateFreeradiusDomainDTO,
                'form' => $form->createView(),
                'context' => FirewallType::DASHBOARD->value,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/autoRenew',
        name: 'admin_dashboard_settings_certs_freeradius_auto_renew',
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsCertificatesManagementFreeradiusAutoRenew(
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

        // Extract the domain from the project
        $domain = $process->getFreeradiusDomainName();
        if (!$this->domainService->isValidDomain($domain)) {
            $this->addFlash('error', $this->translator->trans('notValidDomainOrIP', [
                '%domain%' => $domain,
            ], 'controllers'));

            return $this->redirectToRoute('admin_dashboard_settings_certs_management_freeradius_selection');
        }

        try {
            // Generate certificates (simulated or real)
            // For debug add true on the end for simulation
            $generatedFiles = $this->certificateFreeradiusGenerator->run(
                $domain,
                $user
            );// Add this tag for simulation flag

            foreach ($generatedFiles as $filepath) {
                $uploadedFile = new UploadedFile(
                    $filepath,
                    basename($filepath),
                    null,
                    null,
                    true
                );

                // Map filename to Freeradius enum
                $fileEnum = match (true) {
                    str_contains(
                        $filepath,
                        CertificateFileName::PRIVATE_KEY_PEM->value
                    ) => CertificateFileName::PRIVATE_KEY_PEM,
                    str_contains(
                        $filepath,
                        CertificateFileName::FULL_CHAIN_PEM->value
                    ) => CertificateFileName::FULL_CHAIN_PEM,
                    str_contains(
                        $filepath,
                        CertificateFileName::CHAIN_PEM->value
                    ) => CertificateFileName::CHAIN_PEM,
                    str_contains(
                        $filepath,
                        CertificateFileName::CERT_PEM->value
                    ) => CertificateFileName::CERT_PEM,
                    str_contains(
                        $filepath,
                        CertificateFileName::CA_PEM->value
                    ) => CertificateFileName::CA_PEM,
                    default => null
                };

                if ($fileEnum === null) {
                    continue; // skip unknown files
                }

                // Display name → only the enum values for Freeradius
                $name = $fileEnum->value; // e.g., "CA", "Cert", "Chain", "Full Chain", "Private Key"

                // Determine if it’s a private key storeUploadedFile function
                // Add this "|| $isSimulation;" for simulation testing
                $isPrivateKey = $fileEnum === CertificateFileName::PRIVATE_KEY_PEM;

                $this->certificateStorageService->storeUploadedFile(
                    $uploadedFile,
                    $name, // e.g., "Cert"
                    CertificateMachineType::FREERADIUS->value, // e.g., "FREERADIUS"
                    $process,
                    $isPrivateKey
                );
            }
        } catch (Exception $e) {
            throw new RuntimeException(
                'Failed to generate or store certificates: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        // After the files are validated and the processed, update them once again to add
        $process->setFreeradiusFormCompletedAt(new DateTimeImmutable());
        $process->setFreeradiusConfigAppliedAt(null);
        $process->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->persist($process);
        $this->entityManager->flush();

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_UPLOAD_AUTO->value,
            new DateTime(),
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'by' => $user->getUuid(),
            ]
        );

        $this->addFlash(
            'warning',
            $this->translator->trans('cert_generated_are_lets_encrypt_warning', [], 'controllers')
        );

        $this->addFlash(
            'success',
            $this->translator->trans(
                'freeradiusCertGeneratedSuccessfully',
                ['%domain%' => $domain],
                'controllers'
            )
        );

        // Redirect to the next stage automatically
        return $this->redirectToRoute(
            'admin_dashboard_settings_certs_freeradius_config',
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/config',
        name: 'admin_dashboard_settings_certs_freeradius_config'
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
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
        name: 'admin_dashboard_settings_certs_freeradius_test'
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsCertificatesManagementFreeradiusTest(
        Request $request
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

        // Fetch settings/data needed for the page
        $data = $this->getSettings->getSettings();

        $form = $this->createForm(CertificatesFreeradiusPasteType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Everytime the user tries a new test it will save the used credentials
            $processEntity->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($processEntity);
            $this->entityManager->flush();

            // Build known signing-keys paths
            $basePath = $this->getParameter('kernel.project_dir') . '/signing-keys/';

            $paths = [
                'ca' => $basePath . CertificateFileName::CA_PEM_FILE->value,
                'cert' => $basePath . CertificateFileName::CERT_PEM_FILE->value,
                'chain' => $basePath . CertificateFileName::CHAIN_PEM_FILE->value,
                'fullchain' => $basePath . CertificateFileName::FULL_CHAIN_PEM_FILE->value,
                'privkey' => $basePath . CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
            ];

            // Validate all exist
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
                    $form->getData()['certificates'] ?? ''
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

        $formFinishProcess = $this->createForm(SimpleSubmitFormType::class);
        $formFinishProcess->handleRequest($request);
        if ($formFinishProcess->isSubmitted() && $formFinishProcess->isValid()) {
            $session = $request->getSession();
            $session->remove(SessionStatus::SYSTEM_RESET_REQUEST->value);
            $processEntity->setStatus(ProcessStatusType::COMPLETED);
            $this->entityManager->persist($processEntity);
            $this->entityManager->flush();

            // Redirect to the next stage automatically
            return $this->redirectToRoute(
                'admin_dashboard_settings_certs_management',
            );
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/test.html.twig',
            [
                'data' => $data,
                'processState' => $processState,
                'process' => $processState['process'],
                'form' => $form->createView(),
                'formFinishProcess' => $formFinishProcess->createView(),
            ]
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface|RandomException
     */
    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/cloudflare/dnsChallenge',
        name: 'admin_dashboard_settings_certs_freeradius_cloudflare_dnsChallenge',
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
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

            // Check if the uploaded cert is a EV
            if (in_array('CERTIFICATE_NOT_EV_WARNING', $dto->notices, true)) {
                $process->setIsFreeradiusCertEV(false);
                $this->addFlash(
                    'warning',
                    $this->translator->trans('not_ev_warning', [], 'controllers')
                );
            } else {
                $process->setIsFreeradiusCertEV(true);
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

            if ($dto->ca instanceof UploadedFile) {
                // Save on the tmp folder the uploaded certificates after the validation
                $this->certificateStorageService->storeUploadedFile(
                    $dto->ca,
                    CertificateFileName::CA_PEM->value,
                    CertificateMachineType::FREERADIUS->value,
                    $process,
                    true
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

    #[Route(
        '/dashboard/settings/certificatesManagement/freeradius/skipTest',
        name: 'admin_dashboard_settings_certs_freeradius_skipTest'
    )]
    #[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
    public function settingsCertificatesManagementFreeradiusSkipTest(): Response
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

        $processEntity->setFreeradiusTestResult(CertificateTestResult::PASSED);
        $this->entityManager->persist($processEntity);
        $this->entityManager->flush();

        return $this->redirectToRoute('admin_page');
    }
}
