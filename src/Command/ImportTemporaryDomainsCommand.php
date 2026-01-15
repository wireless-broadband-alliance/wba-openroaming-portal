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

            $response = $this->httpClient->request('GET', $url);
            $content = $response->getContent();

            $progressBar = new ProgressBar($output);
            $progressBar->setFormat(' %current% domains processed');
            $progressBar->start();

            foreach ($this->parseDomains($content) as $rawDomain) {
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

                // Flush in batches
                if ($processed % $this->batchSize === 0) {
                    $this->flushBatch($runAt, $batchUpdates);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln('');
        }

        // Final flush
        $this->flushBatch($runAt, $batchUpdates);

        // Remove stale domains
        $deleted = $this->domainBlacklistRepository->deleteStale(DomainOrigin::LINK);

        $output->writeln(
            "<info>Imported {$processed} domains. Removed {$deleted} stale domains.</info>"
        );

        return Command::SUCCESS;
    }

    private function flushBatch(DateTimeImmutable $runAt, array &$batchUpdates): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();

        if ($batchUpdates !== []) {
            $this->domainBlacklistRepository->batchTouchLastSeen($batchUpdates, DomainOrigin::LINK, $runAt);
            $batchUpdates = [];
        }

        gc_collect_cycles();
    }

    /**
     * Parse content and return domains.
     * Supports JSON array, CSV, TXT (one per line)
     */
    private function parseDomains(string $content): iterable
    {
        $content = trim($content);

        // Try JSON first
        if (str_starts_with($content, '[')) {
            $json = json_decode($content, true);
            if (is_array($json)) {
                foreach ($json as $domain) {
                    yield (string)$domain;
                }
                return;
            }
        }

        // Split lines
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // CSV: pick first column
            if (str_contains($line, ',')) {
                $row = str_getcsv($line, escape: '\\');
                if (isset($row[0]) && ($row[0] !== '' && $row[0] !== '0')) {
                    yield $row[0];
                    continue;
                }
            }

            // TXT fallback
            yield $line;
        }
    }
}
