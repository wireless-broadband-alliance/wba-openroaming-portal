<?php

namespace App\Controller;

use App\Entity\SamlProvider;
use App\Entity\User;
use App\Form\SamlProviderType;
use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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

    /**
     * @throws Exception
     */
    #[Route('/dashboard/saml-provider', name: 'admin_dashboard_saml_provider')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] string $sort = 'createdAt',
        #[MapQueryParameter] string $order = 'desc',
        #[MapQueryParameter] ?int $count = 7,
        #[MapQueryParameter] ?string $filter = 'all',
        #[MapQueryParameter] ?string $searchTerm = null // Search term
    ): Response {
        // Retrieve settings for rendering in the template
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Validate the $count parameter
        if (!is_int($count) || $count <= 0) {
            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        // Fetch the filtered, sorted, and paginated providers using the repository
        $paginator = $this->samlProviderRepository->searchWithFilters(
            $filter,
            $searchTerm,
            $sort,
            $order,
            $page,
            $count
        );

        // Retrieve SAML Provider results for the current page
        $samlProviders = iterator_to_array($paginator->getIterator());

        // Count the total number of SAML Providers
        $totalProviders = $this->samlProviderRepository->countSamlProviders($searchTerm);
        $activeProvidersCount = $this->samlProviderRepository->countSamlProviders($searchTerm, 'active');
        $inactiveProvidersCount = $this->samlProviderRepository->countSamlProviders($searchTerm, 'inactive');

        // Calculate the total number of pages
        $perPage = $count;
        $totalPages = ceil($totalProviders / $perPage);

        return $this->render('admin/saml_provider.html.twig', [
            'data' => $data,
            'samlProviders' => $samlProviders,
            'current_user' => $this->getUser(),
            'totalProviders' => $totalProviders,
            'currentPage' => $page,
            'countPerPage' => $count,
            'activeSort' => $sort,
            'activeOrder' => $order,
            'filter' => $filter,
            'searchTerm' => $searchTerm,
            'allProvidersCount' => $totalProviders,
            'activeProviderCount' => $activeProvidersCount,
            'inactiveProvidersCount' => $inactiveProvidersCount,
            'totalPages' => $totalPages
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
