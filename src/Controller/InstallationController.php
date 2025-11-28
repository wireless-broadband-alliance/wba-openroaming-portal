<?php

namespace App\Controller;

use App\DTO\AdminConfigDTO;
use App\DTO\DbSetupDTO;
use App\DTO\SettingsDTO;
use App\Entity\Event;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\DataBaseSetupType;
use App\Enum\InstallationStep;
use App\Enum\PlatformMode;
use App\Enum\ProcessStatusType;
use App\Enum\SettingName;
use App\Enum\SettingsConfigType;
use App\Form\AdminConfigType;
use App\Form\DbSetupType;
use App\Form\SettingsType;
use App\Form\SimpleSubmitFormType;
use App\Form\TwoFACode;
use App\Repository\EventRepository;
use App\Repository\InstallationProgressRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\DatabaseConnectionService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\InstallationService;
use App\Service\TwoFAService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class InstallationController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly DatabaseConnectionService $databaseConnectionService,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly InstallationService $installationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TwoFAService $twoFAService,
        private readonly InstallationProgressRepository $installationProgressRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EventRepository $eventRepository,
        private readonly CaptchaValidator $captchaValidator,
    ) {
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation',
        name: 'admin_dashboard_settings_certs_installation'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallation(
        Request $request,
    ): Response {
        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $step = $this->installationService->getStep($lastInstallation);
            if ($step === InstallationStep::SETTINGS->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::ADMIN->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
            }
            if ($step === InstallationStep::COMMAND->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_command');
            }
        } else {
            $step = InstallationStep::DATABASE->value;
        }

        $data = $this->getSettings->getSettings();

        $dbDTO = new DbSetupDTO();

        $dbDTO->dbOpenRoamingDbName = 'openroaming';
        $dbDTO->dbFreeradiusDbName = 'radius';
        $dbDTO->dbOpenRoamingPort = 3306;
        $dbDTO->dbFreeradiusPort = 3307;

        $form = $this->createForm(DbSetupType::class, $dbDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $openRoamingDb = $this->databaseConnectionService->buildDatabaseUrl(
                $dbDTO->dbOpenRoamingUserName,
                $dbDTO->dbOpenRoamingPassword,
                $dbDTO->dbOpenRoamingIp,
                $dbDTO->dbOpenRoamingPort,
                $dbDTO->dbOpenRoamingDbName
            );
            $freeradiusDb = $this->databaseConnectionService->buildDatabaseUrl(
                $dbDTO->dbFreeradiusUserName,
                $dbDTO->dbFreeradiusPassword,
                $dbDTO->dbFreeradiusIp,
                $dbDTO->dbFreeradiusPort,
                $dbDTO->dbFreeradiusDbName
            );


            $orConnection = $this->databaseConnectionService->testDatabaseConnection($openRoamingDb);
            //$frConnection = $this->databaseConnectionService->testDatabaseConnection($freeradiusDb);

            $connectionsFailed = [];

            if (!$orConnection) {
                $connectionsFailed[] = 'OpenRoaming';
            }/*
            if (!$frConnection) {
                $connectionsFailed[] = 'Freeradius';
            }*/
            if ($connectionsFailed !== []) {
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'connectionFailed',
                        ['%dbConnections%' => implode(', ', $connectionsFailed)],
                        'controllers'
                    )
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
            }

            if (
                !($lastInstallation instanceof InstallationProgress) ||
                $lastInstallation->getInstallationState() === ProcessStatusType::COMPLETED ||
                $lastInstallation->getInstallationState() === ProcessStatusType::ABORTED
            ) {
                $lastInstallation = new InstallationProgress();
                $lastInstallation->setCreatedAt(new DateTime());
            }

            $lastInstallation->setUpdatedAt(new DateTime());
            $lastInstallation->setDbOpenRoaming($openRoamingDb);
            $lastInstallation->setDbFreeradius($freeradiusDb);
            $lastInstallation->setInstallationState(ProcessStatusType::IN_PROGRESS);
            $this->entityManager->persist($lastInstallation);
            $this->entityManager->flush();


            $orResult = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $openRoamingDb,
                DataBaseSetupType::DATABASE_URL->value
            );
            $radiusResult = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $freeradiusDb,
                DataBaseSetupType::DATABASE_FREERADIUS_URL->value
            );

            if (!$orResult || !$radiusResult) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }

            $this->addFlash(
                'success',
                $this->translator->trans(
                    'dbConnectionApplied',
                    [],
                    'controllers'
                )
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/data_base.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $dbDTO,
                'stages' => $this->installationService->getStepperStatus($step)
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/commands',
        name: 'admin_dashboard_settings_certs_installation_command',
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationDatabaseCommand(
        Request $request,
    ): Response {
        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $step = $this->installationService->getStep($lastInstallation);
            if ($step === InstallationStep::DATABASE->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::SETTINGS->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::ADMIN->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
            }
        } else {
            $step = InstallationStep::DATABASE->value;
        }
        $data = $this->getSettings->getSettings();

        $form = $this->createForm(SimpleSubmitFormType::class);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            if (
                $this->installationService->checkDatabaseSettings($lastInstallation) &&
                $this->installationService->checkSettingsValues($lastInstallation)
            ) {
                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'settingsApplied',
                        [],
                        'controllers'
                    )
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_summary');
            }

            $this->addFlash(
                'error',
                $this->translator->trans(
                    'settingsNotApplied',
                    [],
                    'controllers'
                )
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_command');
        }

        // TODO: in case the user can't run the command add the "chmod +x scripts/update-db-env.sh" command to give to the file permissions to run (Test with Marcelo)

        $commands = [
            [
                'description' => $this->translator->trans(
                    'writeDbSettingsEnv',
                    [],
                    'controllers'
                ),
                'command' => $this->installationService->commandToDataBase($lastInstallation),
            ],
            [
                'description' => $this->translator->trans(
                    'writeSettingsEnv',
                    [],
                    'controllers'
                ),
                'command' => $this->installationService->commandToSettings($lastInstallation),
            ],
            [
                'description' => $this->translator->trans(
                    'createJwtPair',
                    [],
                    'controllers'
                ),
                'command' => 'php bin/console lexik:jwt:generate-keypair --overwrite',
            ]
        ];

        $this->addFlash(
            'error',
            $this->translator->trans(
                'envPermissionDenied',
                [],
                'controllers'
            )
        );

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/manualInstallation/manual_installation.html.twig',
            [
                'data' => $data,
                'stages' => $this->installationService->getStepperStatus($step),
                'commands' => $commands,
                'form' => $form->createView(),
            ]
        );
    }


    /**
     * @throws HttpException
     * @throws LogicException
     * @throws \Exception
     */
    #[Route(
        '/dashboard/settings/certificatesManagement/installation/settings',
        name: 'admin_dashboard_settings_certs_installation_settings'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationSettings(
        Request $request,
        KernelInterface $kernel
    ): Response {
        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $step = $this->installationService->getStep($lastInstallation);
            if ($step === InstallationStep::DATABASE->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::ADMIN->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
            }
            if ($step === InstallationStep::COMMAND->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_command');
            }
        } else {
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
        }
        $data = $this->getSettings->getSettings();

        $settingsDTO = new SettingsDTO();

        $form = $this->createForm(SettingsType::class, $settingsDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $captchaValidation = $this->captchaValidator->validateCredentials($settingsDTO->turnstileSecret);

            if (!$captchaValidation['success']) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('captchaValidationFailed', [], 'controllers')
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }

            $lastInstallation->setUpdatedAt(new DateTime());
            $lastInstallation->setTrustedProxies($settingsDTO->trustedProxies);
            $lastInstallation->setTurnstileKey($settingsDTO->turnstileKey);
            $lastInstallation->setTurnstileSecret($settingsDTO->turnstileSecret);
            if ($settingsDTO->jwtPassphraseEnable) {
                $lastInstallation->setJwtPassphrase($settingsDTO->jwtPassphrase);
            }
            $lastInstallation->setInstallationState(ProcessStatusType::IN_PROGRESS);
            $this->entityManager->persist($lastInstallation);
            $this->entityManager->flush();


            $trustedProxiesPermissions = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                implode(',', $settingsDTO->trustedProxies),
                SettingsConfigType::TRUSTED_PROXIES->value
            );

            $turnstileKeyPermissions = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $settingsDTO->turnstileKey,
                SettingsConfigType::TURNSTILE_KEY->value
            );

            $turnstileSecretPermissions = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $settingsDTO->turnstileSecret,
                SettingsConfigType::TURNSTILE_SECRET->value
            );

            if ($settingsDTO->jwtPassphraseEnable) {
                $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    $settingsDTO->jwtPassphrase,
                    SettingsConfigType::JWT_PASSPHRASE->value
                );
            }

            if (!$trustedProxiesPermissions || !$turnstileKeyPermissions || !$turnstileSecretPermissions) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_command');
            }

            // JWT Verification
            try {
                $application = new Application($kernel);
                $application->setAutoExit(false);

                if ($settingsDTO->jwtPassphraseEnable) {
                    $input = new ArrayInput([
                        'command' => 'lexik:jwt:generate-keypair',
                        '--overwrite' => true,
                        '--passphrase' => $settingsDTO->jwtPassphrase,
                    ]);
                } else {
                    $input = new ArrayInput([
                        'command' => 'lexik:jwt:generate-keypair',
                        '--overwrite' => true,
                    ]);
                }

                $output = new BufferedOutput();
                if (!defined('STDIN')) {
                    define('STDIN', fopen('php://stdin', 'r'));
                }

                $application->run($input, $output);
                // $result = $output->fetch();

                $privateKeyPath = $this->getParameter('kernel.project_dir') . '/config/jwt/private.pem';
                $publicKeyPath = $this->getParameter('kernel.project_dir') . '/config/jwt/public.pem';

                $success = false;

                if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
                    $privateKeyContent = file_get_contents($privateKeyPath);
                    $publicKeyContent = file_get_contents($publicKeyPath);


                    if (
                        str_starts_with(trim($privateKeyContent), '-----BEGIN ENCRYPTED PRIVATE KEY-----') &&
                        str_starts_with(trim($publicKeyContent), '-----BEGIN PUBLIC KEY-----')
                    ) {
                        $success = true;
                    }
                }

                if ($success) {
                    $this->addFlash(
                        'success',
                        $this->translator->trans('jwtSuccessfully', [], 'controllers')
                    );
                    return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
                }
                $this->addFlash(
                    'error',
                    $this->translator->trans('jwtFailed', [], 'controllers')
                );

                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
            } catch (Exception) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('jwtFailed', [], 'controllers')
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/settings.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $settingsDTO,
                'stages' => $this->installationService->getStepperStatus($step)
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/admin',
        name: 'admin_dashboard_settings_certs_installation_admin'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationAdmin(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
    ): Response {
        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $step = $this->installationService->getStep($lastInstallation);
            if ($step === InstallationStep::DATABASE->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::SETTINGS->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::COMMAND->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_command');
            }
        } else {
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
        }

        $data = $this->getSettings->getSettings();

        $adminConfigDTO = new AdminConfigDTO();

        if ($lastInstallation->getEmailAdmin() !== null) {
            $adminConfigDTO->email = $lastInstallation->getEmailAdmin();
        }

        $form = $this->createForm(AdminConfigType::class, $adminConfigDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO: change this function when super admin feature is added!!
            $adminUser = $this->userRepository->findAdmin();

            $adminEmail = $adminConfigDTO->email;
            $adminPassword = $adminConfigDTO->password;

            if ($adminUser instanceof User) {
                $hashedPassword = $userPasswordHasher->hashPassword($adminUser, $adminPassword);
                $lastInstallation->setUpdatedAt(new DateTime());
                $lastInstallation->setEmailAdmin($adminEmail);
                $lastInstallation->setPasswordAdmin($hashedPassword);
                $lastInstallation->setInstallationState(ProcessStatusType::IN_PROGRESS);
                $this->entityManager->persist($lastInstallation);
                $this->entityManager->flush();
            }

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin_sendCode');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/admin.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $adminConfigDTO,
                'stages' => $this->installationService->getStepperStatus($step)
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/admin/sendCode',
        name: 'admin_dashboard_settings_certs_installation_admin_sendCode'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function sendCode(
        Request $request,
    ): RedirectResponse {
        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $step = $this->installationService->getStep($lastInstallation);
            if ($step === InstallationStep::DATABASE->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::SETTINGS->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            $admin = $this->userRepository->findAdmin();
            if ($admin instanceof User) {
                if ($this->installationService->canSendCode($lastInstallation, $admin)) {
                    $this->installationService->sendAdminConfirmationCode($lastInstallation);
                    $eventMetaData = [
                        'platform' => PlatformMode::LIVE->value,
                        'user_agent' => $request->headers->get('User-Agent'),
                        'uuid' => $admin->getUuid(),
                        'ip' => $request->getClientIp(),
                    ];
                    $this->eventActions->saveEvent(
                        $admin,
                        AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_SENT->value,
                        new DateTime(),
                        $eventMetaData
                    );

                    $this->addFlash(
                        'success',
                        $this->translator->trans('codeSentSuccessfully', [], 'controllers')
                    );
                } else {
                    $interval_minutes = $this->twoFAService->timeLeftToResendCode(
                        $admin,
                        AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_SENT->value
                    );

                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'codeAlreadySent',
                            [
                                '%minutes%' => $interval_minutes,
                            ],
                            'controllers'
                        )
                    );
                }
            }
        } else {
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
        }

        return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin_confirmation');
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/admin/confirmation',
        name: 'admin_dashboard_settings_certs_installation_admin_confirmation'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationAdminConfirmation(
        Request $request,
    ): RedirectResponse|Response {
        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $step = $this->installationService->getStep($lastInstallation);
            if ($step === InstallationStep::DATABASE->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::SETTINGS->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::ADMIN->value && !($lastInstallation->getEmailAdmin())) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
            }
        } else {
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
        }

        $data = $this->getSettings->getSettings();

        $form = $this->createForm(TwoFACode::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $code = $data["code"];

            if ($code === $lastInstallation->getConfirmCodeAdmin()) {
                $adminUser = $this->userRepository->findAdmin();
                $lastInstallation->setAdminConfirmation(true);
                if ($adminUser instanceof User) {
                    $adminUser->setEmail($lastInstallation->getEmailAdmin());
                    $adminUser->setPassword($lastInstallation->getPasswordAdmin());
                }

                $this->entityManager->persist($adminUser);
                $this->entityManager->persist($lastInstallation);
                $this->entityManager->flush();

                $this->addFlash(
                    'success',
                    $this->translator->trans('adminConfirmedSuccessfully', [], 'controllers')
                );

                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_summary');
            }
            $this->addFlash(
                'error',
                $this->translator->trans('invalidCodeMessage', [], 'controllers')
            );
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/confirm_admin.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'stages' => $this->installationService->getStepperStatus($step)
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/summary',
        name: 'admin_dashboard_settings_certs_installation_summary'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function installationSummary(): RedirectResponse|Response
    {
        $lastInstallation = $this->installationProgressRepository->getLast();
        if ($lastInstallation instanceof InstallationProgress) {
            $step = $this->installationService->getStep($lastInstallation);
            if ($step === InstallationStep::DATABASE->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::SETTINGS->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
            if ($step === InstallationStep::ADMIN->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
            }
            if ($step === InstallationStep::COMMAND->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_command');
            }
        } else {
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
        }

        $installationDTO = $this->installationService->fillDto($lastInstallation);

        $data = $this->getSettings->getSettings();

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/summary.html.twig',
            [
                'data' => $data,
                'Installation' => $installationDTO,
                'stages' => $this->installationService->getStepperStatus($step)
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/abortProcess',
        name: 'admin_dashboard_settings_certs_installation_abortProcess',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function abortProcess(): RedirectResponse
    {
        $lastInstallation = $this->installationService->lastInstallation();
        // If there's no active installation process
        if (!$lastInstallation instanceof InstallationProgress) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'noActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                )
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
        }

        // Check if installation is in a state that can be aborted
        if ($lastInstallation->getInstallationState() !== ProcessStatusType::IN_PROGRESS) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'noActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                )
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
        }

        // Abort the process
        $lastInstallation->setInstallationState(ProcessStatusType::ABORTED);
        $lastInstallation->setUpdatedAt(new DateTime());

        $this->entityManager->persist($lastInstallation);
        $this->entityManager->flush();

        // Reset the system to the last valid installation config
        $this->installationService->resetToLastInstallation();

        $this->addFlash(
            'error',
            $this->translator->trans(
                'certificateProcessAborted',
                [],
                'controllers'
            )
        );

        return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
    }

    /**
     * @throws \DateMalformedStringException
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    #[Route(
        '/dashboard/settings/certificatesManagement/installation/admin/confirmation/resend',
        name: 'admin_dashboard_settings_certs_installation_admin_confirmation_resend',
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function resendCode(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $this->addFlash(
                'error',
                $this->translator->trans('onlyAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }
        // Handle access restrictions based on the context
        $timeToResetAttempts = (int)$this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value]
        )->getValue();
        $nrAttempts = (int)$this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value]
        )->getValue();
        $timeIntervalToResendCode = (int)$this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value]
        )->getValue();
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeToResetAttempts . ' minutes');

        $eventType = AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_RESENT->value;

        if (
            $this->twoFAService->canResendCode($user, $eventType) &&
            $this->twoFAService->timeIntervalToResendCode($user, $eventType)
        ) {
            $lastInstallation = $this->installationService->lastInstallation();
            if ($lastInstallation instanceof InstallationProgress) {
                $this->installationService->sendAdminConfirmationCode($lastInstallation);
                $eventMetaData = [
                    'platform' => PlatformMode::LIVE->value,
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $user->getUuid(),
                    'ip' => $request->getClientIp(),
                ];
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_RESENT->value,
                    new DateTime(),
                    $eventMetaData
                );
                $attempts = $this->eventRepository->find2FACodeAttemptEvent(
                    $user,
                    $nrAttempts,
                    $limitTime,
                    $eventType
                );
                $attemptsLeft = $nrAttempts - count($attempts);
                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'codeResentSuccessfully',
                        [
                            '%attempts%' => $attemptsLeft
                        ],
                        'controllers'
                    )
                );
                // TODO Remove this flash message in Prod!!
                if ($_ENV['APP_ENV'] === 'dev') {
                    $this->addFlash(
                        'error',
                        'Your code is: ' . $user->getTwoFAcode()
                    );
                }
            }
        } else {
            $lastEvent = $this->eventRepository->findLatest2FACodeAttemptEvent(
                $user,
                $eventType
            );
            $now = new DateTime();
            // Suppose $lastAttemptTime is DateTimeInterface
            $lastAttemptTime = $lastEvent instanceof Event
                ? $lastEvent->getEventDatetime()
                : new DateTime(); // fallback

            // Ensure $limitTime is a DateTime instance
            $limitTime = $lastAttemptTime instanceof DateTime
                ? clone $lastAttemptTime
                : new DateTime($lastAttemptTime->format('Y-m-d H:i:s')); // convert interface to DateTime
            if (!$this->twoFAService->canResendCode($user, $eventType)) {
                $limitTime->modify('+' . $timeToResetAttempts . ' minutes');
                $interval = date_diff($now, $limitTime);
                $interval_minutes = $interval->days * 1440;
                $interval_minutes += $interval->h * 60;
                $interval_minutes += $interval->i;

                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'attemptsExceeded',
                        ['%minutes%' => $interval_minutes],
                        'controllers'
                    )
                );
            } else {
                $limitTime->modify('+' . $timeIntervalToResendCode . ' seconds');
                $interval = date_diff($now, $limitTime);
                $interval_seconds = $interval->days * 1440;
                $interval_seconds += $interval->h * 60;
                $interval_seconds += $interval->i;
                $interval_seconds += $interval->s;

                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'errorAdminWait',
                        ['%time%' => $interval_seconds],
                        'controllers'
                    )
                );
            }
        }
        return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin_confirmation');
    }
}
