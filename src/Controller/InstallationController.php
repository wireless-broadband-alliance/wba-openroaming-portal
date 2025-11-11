<?php

namespace App\Controller;

use App\DTO\AdminConfigDTO;
use App\DTO\DbSetupDTO;
use App\DTO\SettingsDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\DataBaseSetupType;
use App\Enum\InstallationProgressType;
use App\Enum\InstallationStep;
use App\Enum\PlatformMode;
use App\Enum\SettingsConfigType;
use App\Form\AdminConfigType;
use App\Form\DbSetupType;
use App\Form\SettingsType;
use App\Form\TwoFACode;
use App\Repository\InstallationProgressRepository;
use App\Repository\UserRepository;
use App\Service\DatabaseConnectionService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\InstallationService;
use App\Service\TwoFAService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelInterface;
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
    ) {
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation',
        name: 'admin_dashboard_settings_certs_installation'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallation(
        Request $request
    ): Response {
        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $step = $this->installationService->getStep($lastInstallation);
            if ($step === InstallationStep::ADMIN->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
            }
            if ($step === InstallationStep::SETTINGS->value) {
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
            }
        }
        $data = $this->getSettings->getSettings();

        $dbDTO = new DbSetupDTO();

        $form = $this->createForm(DbSetupType::class, $dbDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $openRoamingDb = $this->databaseConnectionService->buildDatabaseUrl(
                $dbDTO->dbOpenRoamingUserName,
                $dbDTO->dbOpenRoamingPassword,
                $dbDTO->dbOpenRoamingIp,
                $dbDTO->dbOpenRoamingPort,
                'openroaming'
            );
            $freeradiusDb = $this->databaseConnectionService->buildDatabaseUrl(
                $dbDTO->dbFreeradiusUserName,
                $dbDTO->dbFreeradiusPassword,
                $dbDTO->dbFreeradiusIp,
                $dbDTO->dbFreeradiusPort,
                'radius'
            );


            $orConnection = $this->databaseConnectionService->testDatabaseConnection($openRoamingDb);
            $frConnection = $this->databaseConnectionService->testDatabaseConnection($freeradiusDb);

            $connectionsFailed = [];

            if (!$orConnection) {
                $connectionsFailed[] = 'OpenRoaming';
            }
            if (!$frConnection) {
                $connectionsFailed[] = 'Freeradius';
            }

            if ($connectionsFailed !== []) {
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

            if (
                !($lastInstallation instanceof InstallationProgress) ||
                $lastInstallation->getInstallationState() === InstallationProgressType::COMPLETED->value ||
                $lastInstallation->getInstallationState() === InstallationProgressType::ABORTED->value
            ) {
                $lastInstallation = new InstallationProgress();
                $lastInstallation->setCreatedAt(new \DateTime());
            }

            $lastInstallation->setUpdatedAt(new \DateTime());
            $lastInstallation->setDbOpenRoaming($openRoamingDb);
            $lastInstallation->setDbFreeradius($freeradiusDb);
            $lastInstallation->setInstallationState(InstallationProgressType::IN_PROGRESS->value);
            $this->entityManager->persist($lastInstallation);
            $this->entityManager->flush();

            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $openRoamingDb,
                DataBaseSetupType::DATABASE_URL->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $freeradiusDb,
                DataBaseSetupType::DATABASE_FREERADIUS_URL->value
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/dataBase.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $dbDTO,
            ]
        );
    }

    /**
     * @throws HttpException
     * @throws LogicException
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
        } else {
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
        }
        $data = $this->getSettings->getSettings();

        $settingsDTO = new SettingsDTO();

        $form = $this->createForm(SettingsType::class, $settingsDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trustedProxies = $settingsDTO->trustedProxies;
            $turnstileKey = $settingsDTO->turnstileKey;
            $turnstileSecret = $settingsDTO->turnstileSecret;
            $jwtPassphraseEnable = $settingsDTO->jwtPassphraseEnable;
            $jwtPassphrase = $settingsDTO->jwtPassphrase;

            if (!($lastInstallation instanceof InstallationProgress)) {
                $lastInstallation = new InstallationProgress();
                $lastInstallation->setCreatedAt(new \DateTime());
            }
            $lastInstallation->setUpdatedAt(new \DateTime());
            $lastInstallation->setTrustedProxies($trustedProxies);
            $lastInstallation->setTurnstileKey($turnstileKey);
            $lastInstallation->setTurnstileSecret($turnstileSecret);
            if ($jwtPassphraseEnable) {
                $lastInstallation->setJwtPassphrase($jwtPassphrase);
            }
            $lastInstallation->setInstallationState(InstallationProgressType::IN_PROGRESS->value);
            $this->entityManager->persist($lastInstallation);
            $this->entityManager->flush();

            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $trustedProxies,
                SettingsConfigType::TRUSTED_PROXIES->value
            );

            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $turnstileKey,
                SettingsConfigType::TURNSTILE_KEY->value
            );

            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $turnstileSecret,
                SettingsConfigType::TURNSTILE_SECRET->value
            );

            if ($jwtPassphraseEnable) {
                $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    $jwtPassphrase,
                    SettingsConfigType::JWT_PASSPHRASE->value
                );
            }

            // JWT Verification
            try {
                $application = new Application($kernel);
                $application->setAutoExit(false);

                if ($jwtPassphraseEnable) {
                    $input = new ArrayInput([
                        'command' => 'lexik:jwt:generate-keypair',
                        '--overwrite' => true,
                        '--passphrase' => $jwtPassphrase,
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

                $result = $output->fetch();

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
                        'success_admin',
                        $this->translator->trans('jwtSuccessfully', [], 'controllers')
                    );
                    return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
                }
                $this->addFlash(
                    'error_admin',
                    $this->translator->trans('jwtFailed', [], 'controllers')
                );

                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
            } catch (\Exception) {
                $this->addFlash(
                    'error_admin',
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
        } else {
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
        }

        $data = $this->getSettings->getSettings();

        $adminConfigDTO = new AdminConfigDTO();

        $form = $this->createForm(AdminConfigType::class, $adminConfigDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO: change this function when super admin feature is added!!
            $adminUser = $this->userRepository->findAdmin();

            $adminEmail = $adminConfigDTO->email;
            $adminPassword = $adminConfigDTO->password;

            if ($adminUser instanceof User) {
                $hashedPassword = $userPasswordHasher->hashPassword($adminUser, $adminPassword);
                if (!($lastInstallation instanceof InstallationProgress)) {
                    $lastInstallation = new InstallationProgress();
                    $lastInstallation->setCreatedAt(new \DateTime());
                }
                $lastInstallation->setUpdatedAt(new \DateTime());
                $lastInstallation->setEmailAdmin($adminEmail);
                $lastInstallation->setPasswordAdmin($hashedPassword);
                $lastInstallation->setInstallationState(InstallationProgressType::IN_PROGRESS->value);
                $this->entityManager->persist($lastInstallation);
                $this->entityManager->flush();
            }

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin_confirmation');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/admin.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $adminConfigDTO,
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/admin/confirmation',
        name: 'admin_dashboard_settings_certs_installation_admin_confirmation'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationAdminConfirmation(
        Request $request,
    ) {
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
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
        }

        $data = $this->getSettings->getSettings();

        $form = $this->createForm(TwoFACode::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $code = $data["code"];

            if ($lastInstallation && $code === $lastInstallation->getConfirmCodeAdmin()) {
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
                'error_admin',
                $this->translator->trans('invalidCodeMessage', [], 'controllers')
            );
        }
        if ($lastInstallation instanceof InstallationProgress) {
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
                        'success_admin',
                        $this->translator->trans('codeSentSuccessfully', [], 'controllers')
                    );
                } else {
                    $interval_minutes = $this->twoFAService->timeLeftToResendCode(
                        $admin,
                        AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_SENT->value
                    );

                    $this->addFlash(
                        'error_admin',
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
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/ConfirmAdmin.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
            ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/summary',
        name: 'admin_dashboard_settings_certs_installation_summary'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function installationSummary()
    {
        $lastInstallation = $this->installationProgressRepository->getLastCompleted();
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
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_settings');
        }

        $installationDTO = $this->installationService->fillDto($lastInstallation);

        $data = $this->getSettings->getSettings();

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/summary.html.twig',
            [
            'data' => $data,
            'Installation' => $installationDTO,
                ]
        );
    }

    #[Route(
        '/dashboard/settings/certificatesManagement/installation/abortProcess',
        name: 'admin_dashboard_settings_certs_installation_abortProcess'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function abortProcess()
    {
        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $lastInstallation->setInstallationState(InstallationProgressType::ABORTED->value);
            $this->entityManager->persist($lastInstallation);
            $this->entityManager->flush();
        }
        return $this->redirectToRoute('admin_dashboard_settings_certs_management');
    }
}
