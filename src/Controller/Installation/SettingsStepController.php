<?php

namespace App\Controller\Installation;

use App\DTO\SettingsDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\InstallationStep;
use App\Enum\ProcessStatusType;
use App\Enum\SettingsConfigType;
use App\Exception\EncryptionException;
use App\Form\SettingsType;
use App\Service\CaptchaValidator;
use App\Service\DatabaseConnectionService;
use App\Service\EncryptionService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;


#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class SettingsStepController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly DatabaseConnectionService $databaseConnectionService,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly InstallationService $installationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CaptchaValidator $captchaValidator,
        private readonly KernelInterface $kernel,
        private readonly EncryptionService $encryptionService,
        private readonly InstallationFlow $installationFlow,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route(
        '/settings',
        name: 'admin_dashboard_settings_certs_installation_settings',
        methods: ['POST']
    )]
    public function __invoke(Request $request): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();
        if (!$lastInstallation instanceof InstallationProgress) {
            return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
        }
        $step = $this->installationService->getStep($lastInstallation);
        $redirect = $this->installationFlow->redirectIfStep($step, InstallationStep::DATABASE);
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }
        if ($step === InstallationStep::ADMIN->value && !$lastInstallation->getEmailAdmin()) {
            return $this->installationFlow->redirectTo(InstallationStep::ADMIN);
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
            $lastInstallation->setTurnstileKey(
                $this->encryptionService->encrypt($settingsDTO->turnstileKey ?? '')
            );
            $lastInstallation->setTurnstileSecret(
                $this->encryptionService->encrypt($settingsDTO->turnstileSecret ?? '')
            );
            if ($settingsDTO->jwtPassphraseEnable) {
                $lastInstallation->setJwtPassphrase(
                    $this->encryptionService->encrypt($settingsDTO->jwtPassphrase)
                );
            }
            $lastInstallation->setInstallationState(ProcessStatusType::IN_PROGRESS);
            $this->entityManager->persist($lastInstallation);
            $this->entityManager->flush();

            if ($settingsDTO->trustedProxies) {
                $trustedProxiesPermissions = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    implode(',', $settingsDTO->trustedProxies),
                    SettingsConfigType::TRUSTED_PROXIES->value
                );
            }

            if ($settingsDTO->turnstileKey) {
                $turnstileKeyPermissions = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    $settingsDTO->turnstileKey,
                    SettingsConfigType::TURNSTILE_KEY->value
                );
            }

            if ($settingsDTO->turnstileSecret) {
                $turnstileSecretPermissions = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    $settingsDTO->turnstileSecret,
                    SettingsConfigType::TURNSTILE_SECRET->value
                );
            }

            if ($settingsDTO->jwtPassphraseEnable) {
                $this->databaseConnectionService->writeDatabaseUrlToEnv(
                    $settingsDTO->jwtPassphrase,
                    SettingsConfigType::JWT_PASSPHRASE->value
                );
            }

            // JWT Verification
            try {
                $application = new Application($this->kernel);
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
                    define('STDIN', fopen('php://stdin', 'rb'));
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
                        is_string($privateKeyContent) &&
                        is_string($publicKeyContent) &&
                        (str_starts_with(
                                trim($privateKeyContent),
                                '-----BEGIN ENCRYPTED PRIVATE KEY-----'
                            ) &&
                            str_starts_with(
                                trim($publicKeyContent),
                                '-----BEGIN PUBLIC KEY-----'
                            )
                        )
                    ) {
                        $success = true;
                    }
                }

                /** @var User $user */
                $user = $this->getUser();

                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::INSTALLATION_SETTINGS_CONFIG->value,
                    new DateTime(),
                    [
                        EventMetadataKeysType::IP->value => $request->getClientIp(),
                        EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                        EventMetadataKeysType::UUID->value => $user->getUuid(),
                    ]
                );

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
                'stages' => $this->installationService->getStepperStatus($step),
                'message' => $this->translator->trans(
                    'canSkipThisPage',
                    [],
                    'controllers'
                )
            ]
        );
    }
}
