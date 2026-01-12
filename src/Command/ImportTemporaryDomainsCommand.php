<?php

namespace App\Command;

use App\Entity\DomainBlacklist;
use App\Enum\DomainMatchType;
use App\Enum\DomainOrigin;
use App\Repository\DomainBlacklistRepository;
use App\Service\DomainService;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
    private const array SOURCES = [
        'https://raw.githubusercontent.com/nfacha/temporary-email-list/master/list.txt',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainService $domainService,
        private readonly DomainBlacklistRepository $domainBlacklistRepository,
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
        $batchSize = 500;
        $count = 0;

        // Mark all LINK domains as potentially stale
        $this->domainBlacklistRepository->markAllAsStale(DomainOrigin::LINK);

        foreach (self::SOURCES as $url) {
            $response = $this->httpClient->request('GET', $url);
            $content = $response->getContent();

            // Extract domains first to know the total for the progress bar
            $domains = iterator_to_array($this->domainService->extract($content));
            $total = count($domains);

            $output->writeln("<info>Importing domains from: {$url} ({$total} domains)</info>");
            $progressBar = new ProgressBar($output, $total);
            $progressBar->start();

            foreach ($domains as $rawDomain) {
                $domain = $this->domainService->normalize($rawDomain);

                // Skip invalid or empty domains
                if ($domain === '' || !$this->domainService->isValidDomain($domain)) {
                    $progressBar->advance();
                    continue;
                }

                $entity = new DomainBlacklist();
                $entity->setPattern($domain)
                    ->setType(DomainMatchType::EXACT)
                    ->setOrigin(DomainOrigin::LINK)
                    ->setCreatedAt($runAt)
                    ->setLastSeenAt($runAt);

                $this->entityManager->persist($entity);
                $count++;

                if ($count % $batchSize === 0) {
                    try {
                        $this->entityManager->flush();
                    } catch (UniqueConstraintViolationException) {
                        // Ignore duplicates
                    }
                    $this->entityManager->clear();
                }

                $progressBar->advance();
            }

            // Finish the progress bar for this source
            $progressBar->finish();
            $output->writeln(''); // newline after progress bar
        }

        // Final flush after all sources
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Ignore duplicates
        }

        // Sweep stale domains (not present in this run)
        $deleted = $this->domainBlacklistRepository->deleteStale(DomainOrigin::LINK);

        $output->writeln("<info>Imported {$count} domains. Removed {$deleted} stale domains.</info>");

        return Command::SUCCESS;
    }
}
