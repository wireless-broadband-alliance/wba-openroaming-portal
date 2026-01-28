<?php

namespace App\Controller;

use App\DTO\DomainBlacklistDTO;
use App\DTO\SourceBlacklistDTO;
use App\Entity\DomainBlacklist;
use App\Entity\DomainSource;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\DomainMatchType;
use App\Enum\DomainOrigin;
use App\Enum\DomainSourceStatus;
use App\Enum\OperationMode;
use App\Form\DomainBlacklistType;
use App\Form\SourceBlacklistType;
use App\Repository\DomainBlacklistRepository;
use App\Repository\DomainSourceRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class DomainBlacklistController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly GetSettings $getSettings,
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainBlacklistRepository $domainBlacklistRepository,
        private readonly DomainSourceRepository $domainSourceRepository,
        private readonly EventActions $eventActions,
    ) {
    }

    #[Route('/dashboard/settings/domains', name: 'admin_dashboard_settings_domains')]
    #[IsGranted('ROLE_ADMIN')]
    public function domainsManagement(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] string $sortDomainsBlacklist = 'createdAt',
        #[MapQueryParameter] string $sortDomainsSources = 'createdAt',
        #[MapQueryParameter] string $orderDomainsBlacklist = 'desc',
        #[MapQueryParameter] string $orderDomainsSources = 'desc',
        #[MapQueryParameter] ?int $count = 7
    ): Response {
        $searchTerm = $request->query->get('u');
        $filter = $request->query->get('filter', (string) DomainSourceStatus::ALL->value);

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $domainDTO = new DomainBlacklistDTO();

        $domainForm = $this->createForm(DomainBlacklistType::class, $domainDTO);
        $domainForm->handleRequest($request);

        if ($domainForm->isSubmitted() && $domainForm->isValid()) {
            $object = new DomainBlacklist();
            $domainDTO->applyToEntity($object);
            $object->setCreatedAt(new DateTimeImmutable());
            $object->setOrigin(DomainOrigin::MANUAL);
            $this->entityManager->persist($object);
            $this->entityManager->flush();

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::BLACKLIST_DOMAIN_ADDED->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'by' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans(
                    'domainAdded',
                    [
                        '%domain%' => $object->getPattern()
                    ],
                    'controllers'
                )
            );
        }

        $sourceDTO = new SourceBlacklistDTO();
        $sourceForm = $this->createForm(SourceBlacklistType::class, $sourceDTO);
        $sourceForm->handleRequest($request);

        if ($sourceForm->isSubmitted() && $sourceForm->isValid()) {
            $source = new DomainSource($sourceDTO->input);
            $source->setActive(true);
            $source->setDomainMatchType($sourceDTO->matchType);
            $this->entityManager->persist($source);
            $this->entityManager->flush();

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::BLACKLIST_SOURCE_ADDED->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'by' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans(
                    'sourceAdded',
                    [
                        '%source%' => $source->getUrl()
                    ],
                    'controllers'
                )
            );
        }

        // Domains Blacklist
        $domainBlacklist = $this->domainBlacklistRepository->searchWithFilter(
            $filter,
            $sortDomainsBlacklist,
            $orderDomainsBlacklist,
            $searchTerm
        );
        $totalBlacklistDomains = $this->domainBlacklistRepository->countDomains(
            2,
            $searchTerm
        );
        $countExactDomains = $this->domainBlacklistRepository->countDomains(
            DomainMatchType::EXACT->value,
            $searchTerm
        );
        $countSubdomainDomains = $this->domainBlacklistRepository->countDomains(
            DomainMatchType::SUBDOMAIN->value,
            $searchTerm
        );
        $totalBlacklistPages = (int)ceil($totalBlacklistDomains / $count);
        $blacklistOffset = ($page - 1) * $count;
        $domainBlacklistPag = array_slice(
            $domainBlacklist,
            $blacklistOffset,
            $count
        );

        // Domains Sources
        $domainSources = $this->domainSourceRepository->searchWithFilter(
            (int)$filter,
            $sortDomainsSources,
            $orderDomainsSources,
            $searchTerm,
        );
        $countActiveDomainSources = $this->domainSourceRepository->countSources(
            $searchTerm,
            true,
        );
        $countInactiveDomainSources = $this->domainSourceRepository->countSources(
            $searchTerm,
            false,
        );
        $totalDomainSources = count($domainSources);
        $totalSourcePages = (int)ceil($totalDomainSources / $count);
        $sourceOffset = ($page - 1) * $count;
        $domainSourcePag = array_slice(
            $domainSources,
            $sourceOffset,
            $count
        );

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            // Settings
            'data' => $data,

            // Blacklist
            'domains' => $domainBlacklistPag,
            'allDomainsCount' => $totalBlacklistDomains,
            'exactDomainsCount' => $countExactDomains,
            'subdomainDomainsCount' => $countSubdomainDomains,
            'totalBlacklistPages' => $totalBlacklistPages,

            // Sources
            'domainsSources' => $domainSourcePag,
            'countDomainSources' => $totalDomainSources,
            'countActiveDomainSources' => $countActiveDomainSources,
            'countInactiveDomainSources' => $countInactiveDomainSources,
            'totalSourcePages' => $totalSourcePages,

            // UI state
            'activeFilterDomains' => $filter,
            'activeSortDomainsBlacklist' => $sortDomainsBlacklist,
            'activeSortDomainsSources' => $sortDomainsSources,
            'activeOrderDomainsBlacklist' => $orderDomainsBlacklist,
            'activeOrderDomainsSources' => $orderDomainsSources,
            'searchTerm' => $searchTerm,
            'currentPage' => $page,
            'count' => $count,

            // Other
            'export_users' => OperationMode::OFF->value,

            // Forms & DTOs
            'domainsForm' => $domainForm->createView(),
            'domainDTO' => $domainDTO,
            'sourceForm' => $sourceForm->createView(),
            'sourceDTO' => $sourceDTO,
        ]);
    }

    #[Route(
        '/dashboard/settings/domain-blacklist/delete/{id<\d+>}',
        name: 'admin_dashboard_blacklist_delete_domain',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteDomains(
        int $id,
        Request $request,
    ): Response {
        // Fetch user and external auths
        $domain = $this->domainBlacklistRepository->find($id);
        if (!$domain instanceof DomainBlacklist) {
            throw $this->createNotFoundException(
                $this->translator->trans('domainNotFound', [], 'controllers')
            );
        }

        $domain->setOrigin(DomainOrigin::DELETED);

        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        $this->addFlash(
            'success_admin',
            $this->translator->trans(
                'domainDeleted',
                [
                    '%domain%' => $domain->getPattern()
                ],
                'controllers'
            )
        );

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::BLACKLIST_DOMAIN_REMOVED->value,
            new DateTime(),
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'by' => $currentUser->getUuid(),
                'domain-removed' => $domain->getPattern(),
            ]
        );


        // Return to the last page where the user was (with searching filters)
        $lastPage = $request->headers->get('referer', '/dashboard');
        return $this->redirect($lastPage);
    }

    #[Route(
        '/dashboard/settings/domain-source/delete/{id<\d+>}',
        name: 'admin_domain_source_delete',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteDomainsSource(
        int $id,
        Request $request,
    ): Response {
        // Fetch user and external auths
        $domainSource = $this->domainSourceRepository->find($id);
        if (!$domainSource instanceof DomainSource) {
            throw $this->createNotFoundException(
                $this->translator->trans('domainSourceNotFound', [], 'controllers')
            );
        }

        $domainSourceData = $domainSource->getUrl();
        $this->entityManager->remove($domainSource);
        $this->entityManager->flush();

        $this->addFlash(
            'success_admin',
            $this->translator->trans(
                'domainSourceDeleted',
                [
                    '%domain%' => $domainSource->getUrl()
                ],
                'controllers'
            )
        );

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::BLACKLIST_SOURCE_REMOVED->value,
            new DateTime(),
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'by' => $currentUser->getUuid(),
                'domain-source-removed' => $domainSourceData,
            ]
        );

        // Return to the last page where the user was (with searching filters)
        $lastPage = $request->headers->get('referer', '/dashboard');
        return $this->redirect($lastPage);
    }

    #[Route(
        '/dashboard/settings/domain-source/{id<\d+>}/toggle',
        name: 'admin_domain_source_toggle',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleDomainSource(
        int $id,
        Request $request
    ): Response {
        // Fetch user and external auths
        $domainSource = $this->domainSourceRepository->find($id);
        if (!$domainSource instanceof DomainSource) {
            throw $this->createNotFoundException(
                $this->translator->trans('domainSourceNotFound', [], 'controllers')
            );
        }

        // Toggle state
        $domainSource->setActive(!$domainSource->isActive());
        $this->entityManager->flush();

        $isActive = $domainSource->isActive();

        // Flash message
        $this->addFlash(
            'success_admin',
            $this->translator->trans(
                $isActive ? 'domainSourceActivated' : 'domainSourceDeactivated',
                ['%domain%' => $domainSource->getUrl()],
                'controllers'
            )
        );

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Pick correct event type
        $eventType = $isActive
            ? AnalyticalEventType::BLACKLIST_SOURCE_ACTIVATED
            : AnalyticalEventType::BLACKLIST_SOURCE_DEACTIVATED;

        // Save event
        $this->eventActions->saveEvent(
            $currentUser,
            $eventType->value,
            new DateTime(),
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'by' => $currentUser->getUuid(),
                'domain_source_url' => $domainSource->getUrl(),
                'active' => $isActive,
            ]
        );

        return $this->redirect(
            $request->headers->get('referer', '/dashboard')
        );
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/dashboard/settings/domain-source/refresh',
        name: 'admin_domain_source_refresh_all',
        methods: ['GET']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function refreshAllDomainSource(
        Request $request,
        KernelInterface $kernel
    ): Response {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'import:temporary-domains',
        ]);

        $output = new BufferedOutput();

        $exitCode = $application->run($input, $output);

        if ($exitCode !== 0) {
            $this->addFlash(
                'error_admin',
                $this->translator->trans(
                    'domainSourceRefreshAllFailed',
                    [],
                    'controllers'
                )
            );
        } else {
            $this->addFlash(
                'success_admin',
                $this->translator->trans(
                    'allDomainSourceRefreshed',
                    [],
                    'controllers'
                )
            );

            /** @var User $currentUser */
            $currentUser = $this->getUser();

            /** @var User $user */
            $user = $this->entityManager->getReference(User::class, $currentUser->getId());

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::BLACKLIST_SOURCES_MANUAL_REFRESH_ALL->value,
                new DateTime(),
                $eventMetadata
            );
        }

        // Return to the last page where the user was (with searching filters)
        $lastPage = $request->headers->get('referer', '/dashboard');
        return $this->redirect($lastPage);
    }

    /**
     * @throws \Exception
     * @throws ORMException
     */
    #[Route(
        '/dashboard/settings/domain-source/{id<\d+>}/refresh',
        name: 'admin_domain_source_refresh',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function refreshDomainSource(
        int $id,
        Request $request,
        KernelInterface $kernel
    ): Response {
        $domainSource = $this->domainSourceRepository->find($id);
        if (!$domainSource instanceof DomainSource) {
            throw $this->createNotFoundException(
                $this->translator->trans('domainSourceNotFound', [], 'controllers')
            );
        }

        if (!$domainSource->isActive()) {
            $this->addFlash(
                'warning_admin',
                $this->translator->trans('domainSourceInactive', [], 'controllers')
            );

            // Return to the last page where the user was (with searching filters)
            $lastPage = $request->headers->get('referer', '/dashboard');
            return $this->redirect($lastPage);
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'import:temporary-domains',
            '--source' => $id,
        ]);

        $output = new BufferedOutput();

        $exitCode = $application->run($input, $output);

        if ($exitCode !== 0) {
            $this->addFlash(
                'error_admin',
                $this->translator->trans(
                    'domainSourceRefreshFailed',
                    ['%domain%' => $domainSource->getUrl()],
                    'controllers'
                )
            );
        } else {
            $this->addFlash(
                'success_admin',
                $this->translator->trans(
                    'domainSourceRefreshed',
                    ['%domain%' => $domainSource->getUrl()],
                    'controllers'
                )
            );

            /** @var User $currentUser */
            $currentUser = $this->getUser();

            /** @var User $user */
            $user = $this->entityManager->getReference(User::class, $currentUser->getId());

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
                'source' => $domainSource->getUrl(),
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::BLACKLIST_SOURCES_MANUAL_REFRESH->value,
                new DateTime(),
                $eventMetadata
            );
        }

        // Return to the last page where the user was (with searching filters)
        $lastPage = $request->headers->get('referer', '/dashboard');
        return $this->redirect($lastPage);
    }
}
