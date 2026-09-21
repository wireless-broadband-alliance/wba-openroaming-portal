<?php

namespace App\Controller\Installation\Admin;

use App\DTO\AdminConfigDTO;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\InstallationStep;
use App\Enum\ProcessStatusType;
use App\Exception\EncryptionException;
use App\Form\AdminConfigType;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\InstallationFlow;
use App\Service\InstallationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class AdminStepController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly InstallationService $installationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly InstallationFlow $installationFlow,
    ) {
    }

    /**
     * @throws EncryptionException
     */
    #[Route(
        '/admin',
        name: 'admin_dashboard_settings_certs_installation_admin',
        methods: ['GET', 'POST']
    )]
    public function __invoke(Request $request): Response
    {
        $lastInstallation = $this->installationService->lastInstallation();
        if (!$lastInstallation instanceof InstallationProgress) {
            return $this->installationFlow->redirectTo(InstallationStep::DATABASE);
        }

        $step = $this->installationService->getStep($lastInstallation);

        // Redirect away if the active step is NOT Admin
        $redirect = $this->installationFlow->redirectIfStep(
            $step,
            InstallationStep::DATABASE,
            InstallationStep::SETTINGS,
            InstallationStep::SECURITY_TXT,
            InstallationStep::COMMAND,
            InstallationStep::COMPLETED,
        );
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $data = $this->getSettings->getSettings();

        $adminConfigDTO = new AdminConfigDTO();
        if ($lastInstallation->getEmailAdmin() !== null) {
            $adminConfigDTO->email = $lastInstallation->getEmailAdmin();
        }

        $form = $this->createForm(AdminConfigType::class, $adminConfigDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $adminUser = $this->userRepository->findSuperAdmin();

            if ($adminUser instanceof User) {
                $hashedPassword = $this->userPasswordHasher->hashPassword($adminUser, $adminConfigDTO->password);
                $adminUser->setPassword($hashedPassword);
                $this->entityManager->persist($adminUser);

                $lastInstallation->setUpdatedAt(new DateTime());
                $lastInstallation->setEmailAdmin($adminConfigDTO->email);
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
                'stages' => $this->installationService->getStepperStatus($step),
            ]
        );
    }
}
