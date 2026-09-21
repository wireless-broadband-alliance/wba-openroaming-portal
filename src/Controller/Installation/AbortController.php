<?php

namespace App\Controller\Installation;

use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\ProcessStatusType;
use App\Exception\EncryptionException;
use App\Service\EventActions;
use App\Service\InstallationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class AbortController extends AbstractController
{
    public function __construct(
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly InstallationService $installationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route(
        '/abortProcess',
        name: 'admin_dashboard_settings_certs_installation_abortProcess',
        methods: ['POST']
    )]
    public function __invoke(Request $request): RedirectResponse
    {
        $lastInstallation = $this->installationService->lastInstallation();

        // Nothing to abort: no active process, or not in a state that can be aborted
        if (
            !$lastInstallation instanceof InstallationProgress ||
            $lastInstallation->getInstallationState() !== ProcessStatusType::IN_PROGRESS
        ) {
            $this->addFlash(
                'error',
                $this->translator->trans('noActiveProcess', [], 'CertificateProcessCheckerService')
            );

            return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
        }

        $lastInstallation->setInstallationState(ProcessStatusType::ABORTED);
        $lastInstallation->setUpdatedAt(new DateTime());

        $this->entityManager->persist($lastInstallation);
        $this->entityManager->flush();

        // Reset the system to the last valid installation config
        $this->installationService->resetToLastInstallation();

        /** @var User $user */
        $user = $this->getUser();

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::INSTALLATION_CONFIG_ABORTED->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $user->getUuid(),
            ]
        );

        $this->addFlash(
            'error',
            $this->translator->trans('certificateProcessAborted', [], 'controllers')
        );

        return $this->redirectToRoute('admin_dashboard_settings_certs_installation');
    }
}
