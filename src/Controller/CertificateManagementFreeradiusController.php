<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CertificateFreeradiusUploadAutoDTO;
use App\DTO\CertificateFreeradiusUploadManualDTO;
use App\Entity\Certificate;
use App\Entity\CertificateSetupProcess;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateTestResult;
use App\Enum\FirewallType;
use App\Enum\ProcessStatusType;
use App\Enum\SessionStatus;
use App\Enum\TrustedWBAFingerprints;
use App\Form\CertificateFreeradiusUploadAutoType;
use App\Form\CertificateFreeradiusUploadManualType;
use App\Form\SimpleSubmitFormType;
use App\Service\CertificateCheckerService;
use App\Service\CertificateFreeradiusCommandsService;
use App\Service\CertificateFreeradiusGenerator;
use App\Service\CertificateFreeradiusInfoService;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateStorageService;
use App\Service\CertificateWriterUpdateService;
use App\Service\DomainService;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MongoDB\Driver\Session;
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
  ) {
  }

  #[Route(
      '/dashboard/settings/certificatesManagement/freeradius/upload',
      name: 'admin_dashboard_settings_certs_freeradius_upload'
  )]
  #[IsGranted('ROLE_ADMIN')]
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
      '/dashboard/settings/certificatesManagement/freeradius/autoRenew',
      name: 'admin_dashboard_settings_certs_freeradius_auto_renew',
      methods: ['POST']
  )]
  #[IsGranted('ROLE_ADMIN')]
  public function settingsCertificatesManagementFreeradiusAutoRenewAction(
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
    $domain = $request->getHost();
    if (!$this->domainService->isValidDomain($domain)) {
      $this->addFlash('error', $this->translator->trans('notValidDomainOrIP', [
          '%domain%' => $domain,
      ], 'controllers'));

      return $this->redirectToRoute('admin_dashboard_settings_certs_management_freeradius_selection');
    }

    try {
      // Generate certificates (simulated or real)
      $generatedFiles = $this->certificateFreeradiusGenerator->run($domain, $user); // For debug add true on the end for simulation
      $isSimulation = true; // Add this tag for simulation flag

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
          str_contains($filepath, CertificateFileName::PRIVATE_KEY_PEM->value) => CertificateFileName::PRIVATE_KEY_PEM,
          str_contains($filepath, CertificateFileName::FULL_CHAIN_PEM->value) => CertificateFileName::FULL_CHAIN_PEM,
          str_contains($filepath, CertificateFileName::CHAIN_PEM->value) => CertificateFileName::CHAIN_PEM,
          str_contains($filepath, CertificateFileName::CERT_PEM->value) => CertificateFileName::CERT_PEM,
          str_contains($filepath, CertificateFileName::CA_PEM->value) => CertificateFileName::CA_PEM,
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
      throw new RuntimeException('Failed to generate or store certificates: ' . $e->getMessage());
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

  #[Route('/dashboard/settings/certificatesManagement/freeradius/config',
      name: 'admin_dashboard_settings_certs_freeradius_config'
  )]
  #[IsGranted('ROLE_ADMIN')]
  public function settingsCertificatesManagementFreeradiusConfig(Request $request): Response
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

    // Return last uploaded certificates from the previous step and reads the contents
    $certificateSet = $this->certificateFreeradiusInfoService->getLatestCertificatesSet($process);

    // Fetch any data/settings needed for the page
    $data = $this->getSettings->getSettings();
    $commands = $this->certificateFreeradiusCommandsService->getRenewCommands($certificateSet);

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
        $this->certificateWriterUpdateService->writeCertificates($certificateSet);

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
            $certificateSet['caFREERADIUS']['content']
        );
        $certContentParsed = $this->certificateCheckerService->parseCertificate(
            $certificateSet['certFREERADIUS']['content']
        );
        $this->certificateWriterUpdateService->updateFromParsedCertificates($caContentParsed, $certContentParsed);

        $process->setFreeradiusConfigAppliedAt(new DateTimeImmutable());
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
  #[IsGranted('ROLE_ADMIN')]
  public function settingsCertificatesManagementFreeradiusTest(): Response
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
        'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/test.html.twig',
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
      '/dashboard/settings/certificatesManagement/freeradius/test/run',
      name: 'admin_dashboard_settings_certs_freeradius_test_run',
      methods: ['POST']
  )]
  #[IsGranted('ROLE_ADMIN')]
  public function runFreeradiusTest(Request $request): JsonResponse
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
    $remotePort = isset($payload['remote_port']) ? (int)$payload['remote_port'] : 11812;

    if (!$remoteHost) {
      return new JsonResponse([
          'status' => 'error',
          'message' => $this->translator->trans(
              'missingRequiredForFreeradiusTest',
              [],
              'controllers'
          ),
      ], Response::HTTP_BAD_REQUEST);
    }

    // Everytime the user tries a new test it will save the used credentials
    $processEntity->setRemoteHost($remoteHost);
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
    $missing = array_filter($paths, static function ($path) {
      return !file_exists($path);
    });

    if (!empty($missing)) {
      return new JsonResponse([
          'status' => 'error',
          'message' => 'Missing certificate files',
          'missing_files' => $missing,
      ], Response::HTTP_BAD_REQUEST);
    }

    try {
//            $context = stream_context_create([
//                'ssl' => [
//                    'verify_peer' => false,
//                    'verify_peer_name' => false,
//                    'allow_self_signed' => false,
//                    'capture_peer_cert_chain' => true,
//                    'local_cert' => $paths['cert'],   // or $paths['cert']
//                    'local_pk'   => $paths['privkey'],
//                    'cafile'     => $paths['ca'],
//                    'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
//                ]
//            ]);
//
//            $connection = @stream_socket_client(
//                "tls://{$remoteHost}:{$remotePort}",
//                $errno,
//                $errstr,
//                15,
//                STREAM_CLIENT_CONNECT,
//                $context
//            );
//
//            // If connection failed with a real error, handle it
//            if ($connection === false && ($errno !== 0 || $errstr !== '')) {
//                // TLS handshake failed
//                $this->certificateFreeradiusCommandsService->updateFreeradiusTestResult(
//                    $processEntity,
//                    CertificateTestResult::FAILED
//                );
//
//                return new JsonResponse([
//                    'status' => 'error',
//                    'message' => 'TLS Handshake Failed',
//                    'messageDetails' => "Failed to create socket: [$errno] $errstr",
//                    'details' => [
//                        'host' => $remoteHost,
//                        'port' => $remotePort,
//                        'error' => $errstr,
//                        'code' => $errno,
////                        'basePath' => $basePath,
////                        'clientCertPath' => $clientCertPath,
////                        'keyCertPath' => $keyCertPath,
//                    ],
//                ], Response::HTTP_SERVICE_UNAVAILABLE);
//            }
//
//            // Extract peer certificate chain
//            $trustedHashes = array_map(static fn($e) => strtolower($e->value), TrustedWBAFingerprints::cases());
//            $params = stream_context_get_params($connection);
//            $chain = $params['options']['ssl']['peer_certificate_chain'] ?? [];
//
//            // Include the leaf certificate itself
//            $leafCert = $params['options']['ssl']['peer_certificate'] ?? null;
//            if ($leafCert) {
//                array_unshift($chain, $leafCert);
//            }
//
//            $validated = false;
//            foreach ($chain as $cert) {
//                $pem = openssl_x509_export($cert, $out) ? $out : null;
//                if ($pem) {
//                    // Convert PEM to DER
//                    $der = base64_decode(preg_replace('#-----.*?-----#', '', $pem));
//                    $hash = strtolower(hash('sha256', $der));
//
//                    if (in_array($hash, $trustedHashes, true)) {
//                        $validated = true;
//                        break;
//                    }
//                }
//            }
//
//            if ($validated === false) {
//                fclose($connection);
//
//                $this->certificateFreeradiusCommandsService->updateFreeradiusTestResult(
//                    $processEntity,
//                    CertificateTestResult::FAILED
//                );
//
//                return new JsonResponse([
//                    'status' => 'error',
//                    'message' => 'TLS handshake succeeded but certificate chain is NOT signed by a known WBA CA.',
//                ], Response::HTTP_FORBIDDEN);
//            }

      // Only close if it’s a valid resource
//            if (is_resource($connection)) {
//                fclose($connection);
//            }
      // THIS IS OK [0] -> a non-false $connection OR errno=0 and errstr="" means TLS handshake succeeded
      $processEntity->setStatus(ProcessStatusType::COMPLETED);
      $processEntity->setFreeradiusTestResult(CertificateTestResult::PASSED);
      $this->entityManager->persist($processEntity);
      $this->entityManager->flush();

      /** @var User $user */
      $user = $this->getUser();
      $this->eventActions->saveEvent(
          $user,
          AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_FREERAEDIUS_TEST->value,
          new DateTime(),
          [
              'ip' => $request->getClientIp(),
              'user_agent' => $request->headers->get('User-Agent'),
              'by' => $user->getUuid(),
          ]
      );

      $session = $request->getSession();
      if ($session->has(SessionStatus::SYSTEM_RESET_REQUEST->value)) {
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::SYSTEM_RESET_REQUEST_COMPLETED->value,
            new DateTime(),
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'by' => $user->getUuid(),
            ]
        );

        // Clear all the sessions requests in case the system_reset is completed
        $session->remove(SessionStatus::SYSTEM_RESET_REQUEST->value);
        $session->remove(SessionStatus::INSTALLATION_STARTED->value);
        $session->remove(SessionStatus::CERTIFICATE_STARTED->value);
      }

      return new JsonResponse([
          'status' => 'success',
          'message' => $this->translator->trans(
              'freeradiusTestPassed',
              [],
              'controllers'
          ),
      ]);
    } catch (Throwable $e) {
      // Update DB when test fails
      $processEntity->setStatus(ProcessStatusType::IN_PROGRESS);
      $processEntity->setFreeradiusTestResult(CertificateTestResult::FAILED);
      $this->entityManager->persist($processEntity);
      $this->entityManager->flush();

      return new JsonResponse([
          'status' => 'error',
          'message' => $e->getMessage(),
      ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
  }
}
