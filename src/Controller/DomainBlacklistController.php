<?php

namespace App\Controller;

use App\DTO\DomainBlacklistDTO;
use App\Entity\DomainBlacklist;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\DomainMatchType;
use App\Form\DomainBlacklistType;
use App\Repository\DomainBlacklistRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class DomainBlacklistController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly GetSettings $getSettings,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly DomainBlacklistRepository $domainBlacklistRepository,
    ) {
    }

    #[Route('/dashboard/settings/blacklist', name: 'admin_dashboard_settings_blacklist')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $domainBlacklistDB = $this->domainBlacklistRepository->findAll();

        // Initialize DTO from settings
        $dto = new DomainBlacklistDTO($domainBlacklistDB);

        // Create form bound to DTO
        $form = $this->createForm(DomainBlacklistType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $importedDomains = [];
            $invalidDomains = [];

            // Handle import file
            $importFile = $form->get('importFile')->getData();
            if ($importFile) {
                $lines = file($importFile->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    // Validate domain pattern
                    if (!preg_match('/^(\*|\*\.[a-z0-9.-]+\.[a-z]{2,}|[a-z0-9.-]+\.[a-z]{2,})$/i', $line)) {
                        $invalidDomains[] = $line;
                        continue;
                    }

                    [$pattern, $type] = $this->parseDomainInput($line);

                    // Skip duplicates
                    $exist = $this->domainBlacklistRepository->findOneBy(['pattern' => $pattern, 'type' => $type]);
                    if ($exist instanceof DomainBlacklist) {
                        continue;
                    }

                    $domain = new DomainBlacklist();
                    $domain->setPattern($pattern)->setType($type);

                    $this->entityManager->persist($domain);
                    $importedDomains[] = $domain;
                }
            }

            // Handle manual edits (lines)
            $blacklist = $dto->toEntities($domainBlacklistDB);

            // Add/Update the other ones
            foreach ($blacklist as $domain) {
                $this->entityManager->persist($domain);
            }

            // Remove deleted domains
            foreach ($domainBlacklistDB as $domainDB) {
                $result = array_find($blacklist, fn(DomainBlacklist $d) => $d->getId() === $domainDB->getId());
                if (is_null($result)) {
                    $this->entityManager->remove($domainDB);
                }
            }

            $this->entityManager->flush();

            // Flash messages
            $importCount = count($importedDomains);
            if ($importCount > 0) {
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans(
                        'domainBlacklistConfigurationAppliedSuccessfully',
                        [],
                        'controllers'
                    ) . " + {$importCount} domains imported"
                );
            }

            if ($invalidDomains !== []) {
                $this->addFlash(
                    'error_admin',
                    $this->translator->trans(
                        'invalidDomainPatternList',
                        ['%domains%' => implode(', ', $invalidDomains)],
                        'controllers'
                    )
                );
            }

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_DOMAIN_BLACKLIST_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('domainBlacklistConfigurationAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_blacklist');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'formDTO' => $dto,
            'data' => $data,
        ]);
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
