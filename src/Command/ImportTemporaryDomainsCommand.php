<?php

namespace App\Command;

use App\Entity\DomainBlacklist;
use App\Enum\DomainMatchType;
use App\Enum\DomainOrigin;
use App\Repository\DomainBlacklistRepository;
use App\Repository\DomainSourceRepository;
use App\Service\DomainService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'import:temporary-domains',
    description: 'Imports temporary domains from public lists and syncs the blacklist'
)]
class ImportTemporaryDomainsCommand extends Command
{
    private int $batchSize = 500;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainService $domainService,
        private readonly DomainBlacklistRepository $domainBlacklistRepository,
        private readonly DomainSourceRepository $domainSourceRepository,
    ) {
        parent::__construct();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runAt = new DateTimeImmutable();
        $processed = 0;

        // Mark all LINK domains as potentially stale
        $this->domainBlacklistRepository->markAllAsStale(DomainOrigin::LINK);

        // Load existing LINK domains (pattern => true)
        $existingPatterns = $this->domainBlacklistRepository->getAllPatternsByOrigin(DomainOrigin::LINK);

        // Temporary array to hold batch updates for lastSeenAt
        $batchUpdates = [];

        // Fetch active sources
        $sources = $this->domainSourceRepository->findActiveSources();

        foreach ($sources as $source) {
            $url = $source->getUrl();
            $output->writeln("<info>Importing domains from: {$url}</info>");

            $response = $this->httpClient->request('GET', $url, ['buffer' => false]);

            $progressBar = new ProgressBar($output);
            $progressBar->setFormat(' %current% domains processed');
            $progressBar->start();

            foreach ($this->domainService->extract($response->getContent(false)) as $rawDomain) {
                $domain = $this->domainService->normalize($rawDomain);

                if ($domain === '' || !$this->domainService->isValidDomain($domain)) {
                    $progressBar->advance();
                    continue;
                }

                if (isset($existingPatterns[$domain])) {
                    // Existing domain → collect for batch update
                    $batchUpdates[] = $domain;
                } else {
                    // New domain → persist normally
                    $entity = new DomainBlacklist();
                    $entity
                        ->setPattern($domain)
                        ->setType(DomainMatchType::EXACT)
                        ->setOrigin(DomainOrigin::LINK)
                        ->setCreatedAt($runAt)
                        ->setLastSeenAt($runAt);

                    $this->entityManager->persist($entity);
                    $existingPatterns[$domain] = true;
                }

                ++$processed;

                // Flush new domains in batches
                if ($processed % $this->batchSize === 0) {
                    $this->flushNewDomains($runAt, $batchUpdates);
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln('');

            unset($response);
        }

        // Final flush for any remaining domains
        $this->flushNewDomains($runAt, $batchUpdates);

        // Remove stale domains
        $deleted = $this->domainBlacklistRepository->deleteStale(DomainOrigin::LINK);

        $output->writeln(
            "<info>Imported {$processed} domains. Removed {$deleted} stale domains.</info>"
        );

        return Command::SUCCESS;
    }

    /**
     * Flush new domains and batch update lastSeenAt for existing ones
     *
     * @param DateTimeImmutable $runAt
     * @param array $batchUpdates
     */
    private function flushNewDomains(DateTimeImmutable $runAt, array &$batchUpdates): void
    {
        // Flush new persisted domains
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Batch update existing domains
        if (!empty($batchUpdates)) {
            $this->domainBlacklistRepository->batchTouchLastSeen($batchUpdates, DomainOrigin::LINK, $runAt);
            $batchUpdates = [];
        }

        gc_collect_cycles();
    }
}
