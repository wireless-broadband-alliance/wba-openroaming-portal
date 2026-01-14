<?php

namespace App\Command;

use App\Entity\DomainBlacklist;
use App\Entity\DomainSource;
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
        $count = 0;
        $batchSize = $this->batchSize;

        // Mark all LINK domains as potentially stale
        $this->domainBlacklistRepository->markAllAsStale(DomainOrigin::LINK);

        // Fetch all active sources
        $activeSources = $this->domainSourceRepository->findActiveSources();

        foreach ($activeSources as $source) {
            $url = $source->getUrl();
            $response = $this->httpClient->request('GET', $url);

            // Convert iterator to array to know total
            $domains = iterator_to_array($this->domainService->extract($response->getContent()));
            $total = count($domains);

            $output->writeln("<info>Importing {$total} domains from: {$url}</info>");

            $progressBar = new ProgressBar($output, $total);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% ');
            $progressBar->start();

            foreach ($domains as $rawDomain) {
                $domain = $this->domainService->normalize($rawDomain);

                if ($domain === '' || !$this->domainService->isValidDomain($domain)) {
                    $progressBar->advance();
                    continue;
                }

                // Check if domain already exists
                $existing = $this->domainBlacklistRepository->findOneBy([
                    'pattern' => $domain,
                ]);

                if ($existing) {
                    if (
                        $existing->getOrigin() === DomainOrigin::MANUAL ||
                        $existing->getOrigin() === DomainOrigin::DELETED
                    ) {
                        // Skip domains manually added by admin
                        $progressBar->advance();
                        continue;
                    }
                    // Update lastSeenAt for existing LINK domain
                    $existing->setLastSeenAt($runAt);
                    $this->entityManager->persist($existing);
                } else {
                    // Create new LINK domain
                    $entity = new DomainBlacklist();
                    $entity->setPattern($domain)
                        ->setType(DomainMatchType::EXACT)
                        ->setOrigin(DomainOrigin::LINK)
                        ->setCreatedAt($runAt)
                        ->setLastSeenAt($runAt);

                    $this->entityManager->persist($entity);
                }

                $count++;

                // Flush in batches
                if ($count % $batchSize === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln(''); // newline after progress bar
        }

        // Final flush for any remaining domains
        $this->entityManager->flush();

        // Delete stale LINK domains
        $deleted = $this->domainBlacklistRepository->deleteStale(DomainOrigin::LINK);

        $output->writeln("<info>Imported {$count} domains. Removed {$deleted} stale domains.</info>");

        return Command::SUCCESS;
    }
}
