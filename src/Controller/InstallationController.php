<?php

namespace App\Controller;

use App\DTO\AdminConfigDTO;
use App\DTO\DbSetupDTO;
use App\DTO\JwtDTO;
use App\DTO\SettingsDTO;
use App\Enum\DataBaseSetupType;
use App\Enum\SettingsConfigType;
use App\Form\AdminConfigType;
use App\Form\DbSetupType;
use App\Form\JwtType;
use App\Form\SettingsType;
use App\Repository\UserRepository;
use App\Service\DatabaseConnectionService;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelInterface;
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
    ) {
    }

    #[Route('/dashboard/settings/certificatesManagement/installation', name: 'admin_dashboard_settings_certs_installation')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallation(
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();

        $dbDTO = new DbSetupDTO();

        $form = $this->createForm(DbSetupType::class, $dbDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $openRoamingDb = $dbDTO->dbOpenRoaming;
            $freeradiusDb = $dbDTO->dbFreeradius;

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
        $data = $this->getSettings->getSettings();

        $settingsDTO = new SettingsDTO();

        $form = $this->createForm(SettingsType::class, $settingsDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trustedProxies = $settingsDTO->trustedProxies;
            $turnstileKey = $settingsDTO->turnstileKey;
            $turnstileSecret = $settingsDTO->turnstileSecret;

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

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_jwt');
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


    #[Route('/dashboard/settings/certificatesManagement/installation/jwt', name: 'admin_dashboard_settings_certs_installation_jwt')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationJwt(
        Request $request,
        KernelInterface $kernel
    ): Response {
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

    #[Route('/dashboard/settings/certificatesManagement/installation/admin', name: 'admin_dashboard_settings_certs_installation_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsCertificatesManagementInstallationAdmin(
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();

        $adminConfigDTO = new AdminConfigDTO();

        $form = $this->createForm(AdminConfigType::class, $adminConfigDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO: change this function when super admin features is added!!
            $adminUser = $this->userRepository->findAdmin();
            return $this->redirectToRoute('');
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
}