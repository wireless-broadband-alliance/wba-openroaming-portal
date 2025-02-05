<?php

namespace App\Controller;

use App\Entity\SamlProvider;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Form\SamlProviderType;
use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
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
        private readonly EventActions $eventActions,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route('/dashboard/saml-provider', name: 'admin_dashboard_saml_provider')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] string $sort = 'createdAt',
        #[MapQueryParameter] string $order = 'desc',
        #[MapQueryParameter] ?int $count = 7,
        #[MapQueryParameter] ?string $filter = 'all',
    ): Response {
        // Retrieve settings for rendering in the template
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Validate the $count parameter
        if (!is_int($count) || $count <= 0) {
            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        $searchTerm = $request->query->get('s');
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
        $totalProviders = $this->samlProviderRepository->countSamlProviders($searchTerm, $filter);
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
            'count' => $count,
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
            // Find and disable the currently active SAML Provider (if any)
            $previousSamlProvider = $this->samlProviderRepository->findOneBy(['isActive' => true]);
            if ($previousSamlProvider) {
                $previousSamlProvider->setActive(false);
            }

            $samlProvider->setActive(true);
            $samlProvider->setCreatedAt(new DateTime());
            $samlProvider->setUpdatedAt(new DateTime());
            $this->entityManager->persist($samlProvider);
            $this->entityManager->flush();

            $this->addFlash('success_admin', 'SAML Provider added successfully.');

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        return $this->render('admin/shared/saml_providers/_saml_provider_new.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'current_user' => $currentUser,
        ]);
    }

    #[Route('dashboard/saml-provider/edit/{id}', name: 'admin_dashboard_saml_provider_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editSamlProvider(
        int $id,
        Request $request,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Find the new SamlProvider to be enabled
        $samlProvider = $this->samlProviderRepository->find($id);
        if (!$samlProvider) {
            $this->addFlash('error_admin', 'This SAML Provider doesn\'t exist!');
            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        $form = $this->createForm(SamlProviderType::class, $samlProvider);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $samlProvider->setUpdatedAt(new DateTime());
            $this->entityManager->persist($samlProvider);
            $this->entityManager->flush();

            // Log the event metadata (tracking the change)
            $eventMetaData = [
                'platform' => PlatformMode::LIVE,
                'samlProviderEdited' => $samlProvider->getName(),
                'ip' => $request->getClientIp(),
                'by' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::ADMIN_EDITED_SAML_PROVIDER,
                new DateTime(),
                $eventMetaData
            );

            $this->addFlash(
                'success_admin',
                sprintf(
                    'SAML Provider "%s" is now enabled.',
                    $samlProvider->getName()
                )
            );

            $samlProviderName = $samlProvider->getName();
            $this->addFlash(
                'success_admin',
                sprintf('"%s" has been updated successfully.', $samlProviderName)
            );

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        return $this->render('admin/shared/saml_providers/_saml_provider_edit.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'current_user' => $currentUser,
        ]);
    }


    #[Route('dashboard/saml-provider/enable/{id}', name: 'admin_dashboard_saml_provider_enable', methods: ['POST'])]
    public function enableSamlProvider(
        int $id,
        Request $request,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Find the new SamlProvider to be enabled
        $samlProvider = $this->samlProviderRepository->find($id);
        if (!$samlProvider) {
            $this->addFlash('error_admin', 'This SAML Provider doesn\'t exist!');
            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        // Find and disable the currently active SAML Provider (if any)
        $previousSamlProvider = $this->samlProviderRepository->findOneBy(['isActive' => true]);
        if ($previousSamlProvider) {
            $previousSamlProvider->setActive(false);
        }
        $samlProvider->setActive(true);
        $this->entityManager->persist($samlProvider);
        if ($previousSamlProvider) {
            $this->entityManager->persist($previousSamlProvider);
        }
        $this->entityManager->flush();

        // Log the event metadata (tracking the change)
        $eventMetaData = [
            'platform' => PlatformMode::LIVE,
            'samlProviderEnabled' => $samlProvider->getName(),
            'previousSamlProvider' => $previousSamlProvider ? $previousSamlProvider->getName() : 'None',
            'ip' => $request->getClientIp(),
            'by' => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::ADMIN_ENABLED_SAML_PROVIDER,
            new DateTime(),
            $eventMetaData
        );

        $this->addFlash(
            'success_admin',
            sprintf(
                'SAML Provider "%s" is now enabled.',
                $samlProvider->getName()
            )
        );
        return $this->redirectToRoute('admin_dashboard_saml_provider');
    }
}
