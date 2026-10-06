<?php

namespace App\Controller\Installation\Admin;

use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\InstallationStep;
use App\Enum\SessionStatus;
use App\Exception\EncryptionException;
use App\Form\TwoFACode;
use App\Repository\UserRepository;
use App\Security\Voter\UserAuthenticationVoter;
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
class AdminConfirmationController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly InstallationService $installationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly InstallationFlow $installationFlow,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route(
        '/admin/confirmation',
        name: 'admin_dashboard_settings_certs_installation_admin_confirmation'
    )]
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $lastInstallation = $this->installationService->lastInstallation();
        if (!$lastInstallation instanceof InstallationProgress) {
            return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
        }

        $computedStep = $this->installationService->getStep($lastInstallation);
        $redirect = $this->installationFlow->redirectIfStep(
            $computedStep,
            InstallationStep::DATABASE,
            InstallationStep::SETTINGS,
            InstallationStep::SECURITY_TXT,
        );
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        if ($computedStep === InstallationStep::ADMIN && !$lastInstallation->getEmailAdmin()) {
            return $this->installationFlow->redirectTo(InstallationStep::ADMIN);
        }

        $data = $this->getSettings->getSettings();

        $form = $this->createForm(TwoFACode::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{code: string} $formData */
            $formData = $form->getData();
            $code = $formData["code"];

            if ($this->installationService->validateAdminConfirmationCode($lastInstallation, $code)) {
                $adminUser = $this->userRepository->findSuperAdmin();

                $lastInstallation->setAdminConfirmed(true);

                if ($adminUser instanceof User) {
                    $adminUser->setEmail($lastInstallation->getEmailAdmin());
                    $adminUser->setUuid($lastInstallation->getEmailAdmin());
                }

                $this->entityManager->persist($adminUser);
                $this->entityManager->persist($lastInstallation);
                $this->entityManager->flush();

                $session = $request->getSession();
                $session->set(SessionStatus::INSTALLATION_VERIFICATION->value, true);

                /** @var User $user */
                $user = $this->getUser();

                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::INSTALLATION_ADMIN_CONFIG->value,
                    new DateTime(),
                    [
                        EventMetadataKeysType::IP->value => $request->getClientIp(),
                        EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                        EventMetadataKeysType::UUID->value => $user->getUuid(),
                    ]
                );

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
                'stages' => $this->installationService->getStepperStatus($computedStep, $lastInstallation),
            ]
        );
    }
}
