<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CertificateFreeradiusUploadManualDTO;
use App\Entity\CertificateSetupProcess;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateTestResult;
use App\Enum\FirewallType;
use App\Enum\TrustedWBAFingerprints;
use App\Form\CertificateFreeradiusUploadManualType;
use App\Form\SimpleSubmitFormType;
use App\Service\CertificateFreeradiusCommandsService;
use App\Service\CertificateFreeradiusInfoService;
use App\Service\CertificateProcessCheckerService;
use App\Service\CertificateStorageService;
use App\Service\CertificateWriterUpdateService;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
      private readonly EventActions $eventActions,
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
            CertificateMachineType::FREERADIUS->value,
            CertificateFileName::CA_PEM->value,
            $process
        );
      }

      if ($certificateUploadDTO->cert instanceof UploadedFile) {
        // Save on the tmp folder the uploaded certificates after the validation
        $this->certificateStorageService->storeUploadedFile(
            $certificateUploadDTO->cert,
            CertificateMachineType::FREERADIUS->value,
            CertificateFileName::CERT_PEM->value,
            $process
        );
      }

      if ($certificateUploadDTO->chain instanceof UploadedFile) {
        // Save on the tmp folder the uploaded certificates after the validation
        $this->certificateStorageService->storeUploadedFile(
            $certificateUploadDTO->chain,
            CertificateMachineType::FREERADIUS->value,
            CertificateFileName::CHAIN_PEM->value,
            $process
        );
      }

      if ($certificateUploadDTO->fullChain instanceof UploadedFile) {
        // Save on the tmp folder the uploaded certificates after the validation
        $this->certificateStorageService->storeUploadedFile(
            $certificateUploadDTO->fullChain,
            CertificateMachineType::FREERADIUS->value,
            CertificateFileName::FULL_CHAIN_PEM->value,
            $process
        );
      }

      if ($certificateUploadDTO->privKey instanceof UploadedFile) {
        // Save on the tmp folder the uploaded certificates after the validation
        $this->certificateStorageService->storeUploadedFile(
            $certificateUploadDTO->privKey,
            CertificateMachineType::FREERADIUS->value,
            CertificateFileName::PRIVATE_KEY_PEM->value,
            $process,
            true
        );
      }

      // After the files are validated and the processed, update them once again to add
      $process->setFreeradiusFormCompletedAt(new DateTimeImmutable());
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
  )]
  #[IsGranted('ROLE_ADMIN')]
  public function settingsCertificatesManagementFreeradiusAutoRenewAction(Request $request
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
    $certificateUploadDTO = new CertificateFreeradiusUploadAutoDTO();

    // Create & handle form
    $form = $this->createForm(CertificateFreeradiusUploadAutoType::class, $certificateUploadDTO);
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

      // TODO generate the new certs here with the command of the cert bot
      // place them on the var tmp folder -> also update the db and define the unique tag for them
      // only the config should take them and place the new ones on the signign-keys

      // After the files are validated and the processed, update them once again to add
      $process->setFreeradiusFormCompletedAt(new DateTimeImmutable());
      $process->setUpdatedAt(new DateTimeImmutable());

      $this->entityManager->persist($process);
      $this->entityManager->flush();

      /** @var User $user */
      $user = $this->getUser();
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
              'freeradiusCertUploadedSuccessfully',
              [],
              'controllers'
          )
      );

      return $this->redirectToRoute('admin_dashboard_settings_certs_freeradius_config');
    }

    return $this->render(
        'dashboard/shared/settings_actions/certificatesManagement/certificates/freeradius/auto_renew.html.twig',
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

        // TODO make new service to update the settings based on the content of the cert.pem -> need to update the DB
        dd($certContent);

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
      $this->certificateFreeradiusCommandsService->updateFreeradiusTestResult(
          $processEntity,
          CertificateTestResult::PASSED
      );

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
      if ($session->has('system_reset_request')) {
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
        $session->remove('system_reset_request');
        $session->remove('session_installation_started');
        $session->remove('session_certificate_started');
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
      $this->certificateFreeradiusCommandsService->updateFreeradiusTestResult(
          $processEntity,
          CertificateTestResult::FAILED
      );

      return new JsonResponse([
          'status' => 'error',
          'message' => $e->getMessage(),
      ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
  }
}
