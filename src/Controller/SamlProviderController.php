<?php

namespace App\Controller;

use App\Entity\SamlProvider;
use App\Entity\User;
use App\Form\SamlProviderType;
use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\Domain;
use App\Service\GetSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SamlProviderController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly settingRepository $settingRepository,
        private readonly GetSettings $getSettings,
        private readonly SamlProviderRepository $samlProviderRepository,
        private readonly EntityManagerInterface $entityManager,
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

        return $this->render('admin/saml_provider.html.twig', [
            'data' => $data,
            'samlProviders' => $samlProviders,
            'current_user' => $currentUser,
        ]);
    }

    #[Route('dashboard/saml-provider/new', name: 'admin_dashboard_saml_provider_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function addSamlProvider(Request $request): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $samlProvider = new SamlProvider();
        $form = $this->createForm(SamlProviderType::class, $samlProvider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($samlProvider);
            $this->entityManager->flush();

            $this->addFlash('success', 'SAML Provider added successfully.');

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        return $this->render('admin/shared/_saml_provider_new.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'current_user' => $currentUser,
        ]);
    }
}
