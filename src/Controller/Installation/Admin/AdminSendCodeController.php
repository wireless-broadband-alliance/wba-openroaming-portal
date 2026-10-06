<?php

namespace App\Controller\Installation\Admin;

use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\InstallationStep;
use App\Exception\EncryptionException;
use App\Repository\UserRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use App\Service\TwoFAService;
use DateTime;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
#[IsGranted(UserAuthenticationVoter::INSTALLATION_WRITE)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class AdminSendCodeController extends AbstractController
{
    public function __construct(
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly InstallationService $installationService,
        private readonly TwoFAService $twoFAService,
        private readonly InstallationFlow $installationFlow,
    ) {
    }

    /**
     * @throws EncryptionException
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    #[Route('/admin/sendCode', name: 'admin_dashboard_settings_certs_installation_admin_sendCode')]
    public function __invoke(Request $request): RedirectResponse
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
        );
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $admin = $this->userRepository->findSuperAdmin();
        if ($admin instanceof User) {
            if (
                $this->installationService->canSendCode(
                    AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_SENT->value,
                    $admin
                )
            ) {
                $this->installationService->sendAdminConfirmationCode($lastInstallation);

                $this->eventActions->saveEvent(
                    $admin,
                    AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_SENT->value,
                    new DateTime(),
                    [
                        EventMetadataKeysType::IP->value => $request->getClientIp(),
                        EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                        EventMetadataKeysType::UUID->value => $admin->getUuid(),
                    ]
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
                        ['%minutes%' => $interval_minutes],
                        'controllers'
                    )
                );
            }
        }

        return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin_confirmation');
    }
}
