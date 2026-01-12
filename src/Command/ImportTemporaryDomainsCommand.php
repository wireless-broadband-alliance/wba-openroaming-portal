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

        // Process each source
        foreach (self::SOURCES as $url) {
            $response = $this->httpClient->request('GET', $url);
            $content = $response->getContent();

            foreach ($this->domainService->extract($content) as $rawDomain) {
                $domain = $this->domainService->normalize($rawDomain);

                if ($domain === '' || !$this->domainService->isValidDomain($domain)) {
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
            }
        }

        // Final flush
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Ignore duplicates
        }

        // Sweep: remove domains no longer in the list
        $this->domainBlacklistRepository->deleteStale(DomainOrigin::LINK);

        $output->writeln("<info>Imported {$count} domains. Removed stale domains automatically.</info>");

        return Command::SUCCESS;
    }
}
