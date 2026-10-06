<?php

namespace App\Controller\Installation;

use App\DTO\DbSetupDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\DataBaseSetupType;
use App\Enum\EventMetadataKeysType;
use App\Enum\InstallationStep;
use App\Enum\ProcessStatusType;
use App\Enum\SessionStatus;
use App\Exception\EncryptionException;
use App\Form\DbSetupType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\DatabaseConnectionService;
use App\Service\EncryptionService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
#[IsGranted(UserAuthenticationVoter::INSTALLATION_WRITE->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class DatabaseStepController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly DatabaseConnectionService $databaseConnectionService,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly InstallationService $installationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionService $encryptionService,
        private readonly InstallationFlow $installationFlow,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route(
        '',
        name: 'admin_dashboard_settings_certs_installation',
        methods: ['GET', 'POST']
    )]
    public function __invoke(Request $request): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();
        $computedStep = InstallationStep::DATABASE;

        if ($lastInstallation instanceof InstallationProgress) {
            $computedStep = $this->installationService->getStep($lastInstallation);
            $redirect = $this->installationFlow->redirectIfStep(
                $computedStep,
                InstallationStep::SETTINGS,
                InstallationStep::SECURITY_TXT,
                InstallationStep::ADMIN,
                InstallationStep::COMMAND,
                InstallationStep::COMPLETED,
            );

            if ($redirect instanceof RedirectResponse) {
                return $redirect;
            }
        }

        $data = $this->getSettings->getSettings();

        $dbDTO = new DbSetupDTO();
        $dbDTO->dbOpenRoamingUserName = 'openroaming';
        $dbDTO->dbOpenRoamingIp = '127.0.0.1';
        $dbDTO->dbOpenRoamingPort = 3306;
        $dbDTO->dbOpenRoamingDbName = 'openroaming';

        $dbDTO->dbFreeradiusUserName = 'root';
        $dbDTO->dbFreeradiusIp = '127.0.0.1';
        $dbDTO->dbFreeradiusPort = 3306;
        $dbDTO->dbFreeradiusDbName = 'radius';

        $form = $this->createForm(DbSetupType::class, $dbDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $openRoamingDb = $this->databaseConnectionService->buildDatabaseUrl(
                $dbDTO->dbOpenRoamingUserName,
                $dbDTO->dbOpenRoamingPassword,
                $dbDTO->dbOpenRoamingIp,
                $dbDTO->dbOpenRoamingPort,
                $dbDTO->dbOpenRoamingDbName,
                serverVersion: '8.0.44',
                sslMode: 'verify-full',
            );

            $freeradiusDb = $this->databaseConnectionService->buildDatabaseUrl(
                $dbDTO->dbFreeradiusUserName,
                $dbDTO->dbFreeradiusPassword,
                $dbDTO->dbFreeradiusIp,
                $dbDTO->dbFreeradiusPort,
                $dbDTO->dbFreeradiusDbName,
                serverVersion: '8.0.44',
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
                    'error',
                    $this->translator->trans(
                        'connectionFailed',
                        ['%dbConnections%' => implode(', ', $connectionsFailed)],
                        'controllers'
                    )
                );
                return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
            }

            if (
                !($lastInstallation instanceof InstallationProgress) ||
                $lastInstallation->getInstallationState() === ProcessStatusType::COMPLETED ||
                $lastInstallation->getInstallationState() === ProcessStatusType::ABORTED
            ) {
                $lastInstallation = new InstallationProgress();
                $lastInstallation->setCreatedAt(new DateTime());
            }

            // Encrypt connection strings before storing in the database
            $encryptedOpenRoamingDb = $this->encryptionService->encrypt($openRoamingDb);
            $encryptedFreeradiusDb = $this->encryptionService->encrypt($freeradiusDb);

            $lastInstallation->setUpdatedAt(new DateTime());
            $lastInstallation->setDbOpenRoaming($encryptedOpenRoamingDb);
            $lastInstallation->setDbFreeradius($encryptedFreeradiusDb);
            $lastInstallation->setInstallationState(ProcessStatusType::IN_PROGRESS);

            $this->entityManager->persist($lastInstallation);
            $this->entityManager->flush();

            $session = $request->getSession();
            if ($session->has(SessionStatus::SYSTEM_RESET_REQUEST->value)) {
                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::SYSTEM_RESET_REQUEST_IN_PROGRESS->value,
                    new DateTime(),
                    [
                        EventMetadataKeysType::IP->value => $request->getClientIp(),
                        EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                        EventMetadataKeysType::UUID->value => $user->getUuid(),
                    ]
                );
            }

            // Write raw unencrypted DSN to .env
            $orResult = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $openRoamingDb,
                DataBaseSetupType::DATABASE_URL->value
            );
            $radiusResult = $this->databaseConnectionService->writeDatabaseUrlToEnv(
                $freeradiusDb,
                DataBaseSetupType::DATABASE_FREERADIUS_URL->value
            );

            if (!$orResult || !$radiusResult) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('envPermissionDenied', [], 'controllers')
                );
                return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
            }

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::INSTALLATION_DATABASE_CONFIG->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $user->getUuid(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('dbConnectionApplied', [], 'controllers')
            );

            return $this->installationFlow->redirectTo(InstallationStep::SETTINGS);
        }

        return $this->render(
            'dashboard/shared/settings_actions/certificatesManagement/installation/data_base.html.twig',
            [
                'data' => $data,
                'form' => $form->createView(),
                'formDTO' => $dbDTO,
                'stages' => $this->installationService->getStepperStatus($computedStep, $lastInstallation),
            ]
        );
    }
}
