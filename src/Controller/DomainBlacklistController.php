<?php

namespace App\Controller;

use App\DTO\DomainBlacklistDTO;
use App\Entity\DomainBlacklist;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\DomainMatchType;
use App\Enum\DomainOrigin;
use App\Enum\OperationMode;
use App\Form\DomainBlacklistType;
use App\Repository\DomainBlacklistRepository;
use App\Repository\DomainSourceRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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

    #[Route('/dashboard/settings/blacklist', name: 'admin_dashboard_settings_blacklist')]
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
        $filter = $request->query->get('filter', 'all');

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

//        /** @var User $currentUser */
//        $currentUser = $this->getUser();
//
//        // Initialize DTO from settings
//        $domainBlacklistDB = [];
//        $dto = new DomainBlacklistDTO($domainBlacklistDB);
//
//        // Create form bound to DTO
//        $form = $this->createForm(DomainBlacklistType::class, $dto);
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            $importedDomains = [];
//            $invalidDomains = [];
//
//            // Handle import file
//            $importFile = $form->get('importFile')->getData();
//            if ($importFile) {
//                $lines = @file($importFile->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
//                foreach ($lines as $line) {
//                    $line = trim($line);
//                    if ($line === '') {
//                        continue;
//                    }
//
//                    // Validate domain pattern
//                    if (!preg_match('/^(\*|\*\.[a-z0-9.-]+\.[a-z]{2,}|[a-z0-9.-]+\.[a-z]{2,})$/i', $line)) {
//                        $invalidDomains[] = $line;
//                        continue;
//                    }
//
//                    [$pattern, $type] = $this->parseDomainInput($line);
//
//                    // Skip duplicates
//                    $exist = $this->domainBlacklistRepository->findOneBy(['pattern' => $pattern, 'type' => $type]);
//                    if ($exist instanceof DomainBlacklist) {
//                        continue;
//                    }
//
//                    $domain = new DomainBlacklist();
//                    $domain->setPattern($pattern)->setType($type);
//
//                    $this->entityManager->persist($domain);
//                    $importedDomains[] = $domain;
//                }
//            }
//
//            // Handle manual edits (lines)
//            $blacklist = $dto->toEntities($domainBlacklistDB);
//
//            // Add/Update the other ones
//            foreach ($blacklist as $domain) {
//                $this->entityManager->persist($domain);
//            }
//
//            $this->entityManager->flush();
//
//            // Flash messages
//            $importCount = count($importedDomains);
//            if ($importCount > 0) {
//                $this->addFlash(
//                    'success_admin',
//                    $this->translator->trans(
//                        'domainBlacklistConfigurationAppliedSuccessfully',
//                        [],
//                        'controllers'
//                    ) . " + {$importCount} domains imported"
//                );
//            }
//
//            if ($invalidDomains !== []) {
//                $this->addFlash(
//                    'error_admin',
//                    $this->translator->trans(
//                        'invalidDomainPatternList',
//                        ['%domains%' => implode(', ', $invalidDomains)],
//                        'controllers'
//                    )
//                );
//            }
//
//            // Log the event
//            $this->eventActions->saveEvent(
//                $currentUser,
//                AnalyticalEventType::SETTING_DOMAIN_BLACKLIST_REQUEST->value,
//                new DateTime(),
//                [
//                    'ip' => $request->getClientIp(),
//                    'user_agent' => $request->headers->get('User-Agent'),
//                    'uuid' => $currentUser->getUuid(),
//                ]
//            );
//
//            $this->addFlash(
//                'success_admin',
//                $this->translator->trans('domainBlacklistConfigurationAppliedSuccessfully', [], 'controllers')
//            );
//            return $this->redirectToRoute('admin_dashboard_settings_blacklist');
//        }

        // Domains Blacklist
        $domainBlacklist = $this->domainBlacklistRepository->searchWithFilter(
            $filter,
            $sortDomainsBlacklist,
            $orderDomainsBlacklist,
            $searchTerm
        );
        $totalBlacklistDomains = $this->domainBlacklistRepository->countDomains(
            'all',
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
        $countWildcardDomains = $this->domainBlacklistRepository->countDomains(
            DomainMatchType::WILDCARD->value,
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
            $filter,
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
            'wildcardDomainsCount' => $countWildcardDomains,
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

            // Forms
            'domainsForm' => $domainForm->createView(),
            'domainDTO' => $domainDTO,
        ]);
    }

    #[Route('/dashboard/settings/blacklist/delete/{id<\d+>}', name: 'admin_dashboard_blacklist_delete_domain', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteDomains(
        int $id,
        Request $request,
    ): Response {
        // Fetch user and external auths
        $domain = $this->domainBlacklistRepository->find($id);
        if (!$domain) {
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

        // Return to the last page where the user has (with searching filters)
        $lastPage = $request->headers->get('referer', '/dashboard');
        return $this->redirect($lastPage);
    }

    /**
     * Parses a domain input string into pattern + type
     *
     * @return array{0: string, 1: DomainMatchType}
     */
    private function parseDomainInput(string $input): array
    {
        $input = strtolower(trim($input));

        if ($input === '*') {
            return ['*', DomainMatchType::WILDCARD];
        }

        if (str_starts_with($input, '*.')) {
            return [substr($input, 2), DomainMatchType::SUBDOMAIN];
        }

        return [$input, DomainMatchType::EXACT];
    }
}
