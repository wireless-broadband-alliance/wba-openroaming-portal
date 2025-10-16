<?php

namespace App\Controller;

use App\DTO\AdminConfigDTO;
use App\DTO\DbSetupDTO;
use App\DTO\SettingsDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\InstallationProgressType;
use App\Enum\InstallationStep;
use App\Enum\SettingsConfigType;
use App\Form\AdminConfigType;
use App\Form\DbSetupType;
use App\Form\SettingsType;
use App\Form\TwoFACode;
use App\Repository\UserRepository;
use App\Service\DatabaseConnectionService;
use App\Service\GetSettings;
use App\Service\InstallationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly InstallationService $installationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/dashboard/settings/certificatesManagement/installation', name: 'admin_dashboard_settings_certs_installation')]
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
            $openRoamingDb = $dbDTO->dbOpenRoaming;
            $freeradiusDb = $dbDTO->dbFreeradius;

            /*

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
            */
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


            // TODO: write this variables only at the end
            /*
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $openRoamingDb,
                DataBaseSetupType::DATABASE_URL->value
            );
            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $freeradiusDb,
                DataBaseSetupType::DATABASE_FREERADIUS_URL->value
            );*/

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

    /**
     * @throws HttpException
     * @throws LogicException
     */
    #[Route('/dashboard/settings/certificatesManagement/installation/settings', name: 'admin_dashboard_settings_certs_installation_settings')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationSettings(
        Request $request
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

            // TODO: write this variables only at the end
            /*
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
            }*/

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin');
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/settings.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $settingsDTO
            ]
        );
    }

/*
    #[Route('', name: '')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationJwt(
        Request $request,
        KernelInterface $kernel
    ): Response {
        // TODO   warning!!!! unused function  REMOVE THIS AT THE END
        $data = $this->getSettings->getSettings();

        $jwtDTO = new JwtDTO();

        $form = $this->createForm(JwtType::class, $jwtDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $jwtSecretKey = $jwtDTO->jwtSecretKey;
            $jwtPublicKey = $jwtDTO->jwtPublicKey;
            $jwtPassphraseEnable = $jwtDTO->jwtPassphraseEnable;
            $jwtPassphrase = $jwtDTO->jwtPassphrase;

            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $jwtSecretKey,
                SettingsConfigType::JWT_SECRET_KEY->value
            );

            $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $jwtPublicKey,
                SettingsConfigType::JWT_PUBLIC_KEY->value
            );

            if ($jwtPassphraseEnable) {
                $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    $jwtPassphrase,
                    SettingsConfigType::JWT_PASSPHRASE->value
                );
            }

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

                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_jwt');
            } catch (\Exception $exception) {
                $this->addFlash(
                    'error_admin',
                    $this->translator->trans('jwtFailed', [], 'controllers')
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_jwt');
            }
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/jwt.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $jwtDTO
            ]
        );
    }
*/

    #[Route('/dashboard/settings/certificatesManagement/installation/admin', name: 'admin_dashboard_settings_certs_installation_admin')]
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
                'formDTO' => $adminConfigDTO
            ]
        );
    }

    #[Route('/dashboard/settings/certificatesManagement/installation/admin/confirmation', name: 'admin_dashboard_settings_certs_installation_admin_confirmation')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationAdminConfirmation()
    {
        $data = $this->getSettings->getSettings();
        //TODO make validations for resend codes (the user can not spam with emails)

        $lastInstallation = $this->installationService->lastInstallation();
        if ($lastInstallation instanceof InstallationProgress) {
            $this->installationService->sendAdminConfirmationCode($lastInstallation);
        }

        $form = $this->createForm(TwoFACode::class);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $code = $data["code"];

            if ($code === $lastInstallation->getConfirmCodeAdmin()) {
                $this->addFlash(
                    'success',
                    $this->translator->trans()
                );
            }
            $this->addFlash(
                'error_admin',
                $this->translator->trans()
            );
        }

        return $this->render('dashboard/shared/settings_actions/certificatesManagement/installation/ConfirmAdmin.html.twig',
        [
            'data' => $data,
            'form' => $form->createView(),
        ]);

    }
}