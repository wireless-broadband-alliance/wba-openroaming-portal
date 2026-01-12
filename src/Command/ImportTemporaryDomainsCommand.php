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
        $batchSize = 500;

        // Mark all LINK domains as potentially stale
        $this->domainBlacklistRepository->markAllAsStale(DomainOrigin::LINK);

        // Fetch all active sources
        $activeSources = $this->domainSourceRepository->findActiveSources();

        foreach ($activeSources as $source) {
            $url = $source->getUrl();
            $response = $this->httpClient->request('GET', $url);

            $output->writeln("<info>Importing domains from: {$url}</info>");
            $progressBar = new ProgressBar($output);
            $progressBar->start();

            foreach ($this->domainService->extract($response->getContent()) as $rawDomain) {
                $domain = $this->domainService->normalize($rawDomain);

                if ($domain === '' || !$this->domainService->isValidDomain($domain)) {
                    $progressBar->advance();
                    continue;
                }

                // Use DQL bulk update instead of loading each entity
                $existing = $this->domainBlacklistRepository->findOneBy([
                    'pattern' => $domain,
                ]);

                if ($existing) {
                    if ($existing->getOrigin() === DomainOrigin::MANUAL) {
                        $progressBar->advance();
                        continue;
                    }

                    // Existing LINK domain
                    $existing->setLastSeenAt($runAt);
                } else {
                    // New LINK domain
                    $entity = new DomainBlacklist();
                    $entity->setPattern($domain)
                        ->setType(DomainMatchType::EXACT)
                        ->setOrigin(DomainOrigin::LINK)
                        ->setCreatedAt($runAt)
                        ->setLastSeenAt($runAt);

                    $this->entityManager->persist($entity);
                }

                $count++;

                if ($count % $batchSize === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln('');
        }

        // Final flush
        $this->entityManager->flush();

        // Delete stale domains
        $deleted = $this->domainBlacklistRepository->deleteStale(DomainOrigin::LINK);


        $output->writeln("<info>Imported {$count} domains. Removed {$deleted} stale domains.</info>");

        return Command::SUCCESS;
    }
}
