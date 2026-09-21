<?php

namespace App\Controller\Installation;

use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\InstallationStep;
use App\Exception\EncryptionException;
use App\Form\SimpleSubmitFormType;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class CommandStepController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly InstallationService $installationService,
        private readonly InstallationFlow $installationFlow,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route('/commands', name: 'admin_dashboard_settings_certs_installation_command')]
    public function __invoke(Request $request): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();
        if (!$lastInstallation instanceof InstallationProgress) {
            return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
        }

        $step = $this->installationService->getStep($lastInstallation);
        $redirect = $this->installationFlow->redirectIfStep(
            $step,
            InstallationStep::DATABASE,
            InstallationStep::SETTINGS,
            InstallationStep::SECURITY_TXT,
            InstallationStep::ADMIN,
        );
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
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
                    $this->translator->trans('settingsApplied', [], 'controllers')
                );
                return $this->redirectToRoute('admin_dashboard_settings_certs_installation_summary');
            }

            /** @var User $user */
            $user = $this->getUser();

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::INSTALLATION_COMMAND_CONFIG->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $user->getUuid(),
                ]
            );

            $this->addFlash(
                'error',
                $this->translator->trans('settingsNotApplied', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_certs_installation_command');
        }

        $commands = [
            [
                'description' => $this->translator->trans('chmodDbScript', [], 'controllers'),
                'command' => 'chmod +x /var/www/openroaming/scripts/update-db-env.sh',
            ],
            [
                'description' => $this->translator->trans('chmodSettingsScript', [], 'controllers'),
                'command' => 'chmod +x /var/www/openroaming/scripts/update-settings-env.sh',
            ],
            [
                'description' => $this->translator->trans('writeDbSettingsEnv', [], 'controllers'),
                'command' => $this->installationService->commandToDataBase($lastInstallation),
            ],
            [
                'description' => $this->translator->trans('writeSettingsEnv', [], 'controllers'),
                'command' => $this->installationService->commandToSettings($lastInstallation),
            ],
            [
                'description' => $this->translator->trans('createJwtPair', [], 'controllers'),
                'command' => 'php bin/console lexik:jwt:generate-keypair --overwrite',
            ],
        ];

        $this->addFlash(
            'error',
            $this->translator->trans('envPermissionDenied', [], 'controllers')
        );

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/'
            . 'installation/manualInstallation/manual_installation.html.twig',
            [
                'data' => $data,
                'stages' => $this->installationService->getStepperStatus($step),
                'commands' => $commands,
                'form' => $form->createView(),
            ]
        );
    }
}
