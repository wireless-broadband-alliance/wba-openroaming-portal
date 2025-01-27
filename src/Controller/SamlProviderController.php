<?php

namespace App\Controller;

use App\Entity\SamlProvider;
use App\Entity\User;
use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SamlProviderController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly settingRepository $settingRepository,
        private readonly GetSettings $getSettings,
        private readonly SamlProviderRepository $samlProviderRepository,
        // private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('dashboard/saml-provider', name: 'admin_dashboard_saml_provider')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $samlProviders = $this->samlProviderRepository->findAll();

        return $this->render('admin/settings_actions.html.twig', [
            'data' => $data,
            'samlProviders' => $samlProviders,
            'current_user' => $currentUser,
        ]);
    }
}
