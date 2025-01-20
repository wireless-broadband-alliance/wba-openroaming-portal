<?php

namespace App\Command;

use App\Service\SamlProviderConfigService;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugSamlConfigCommand extends Command
{
    protected static $defaultName = 'app:debug-saml-config';

    private SamlProviderConfigService $service;

    public function __construct(SamlProviderConfigService $service)
    {
        $this->service = $service;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Debug SAML configuration resolution.');
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->service->getDefaultProviderConfig();
        $output->writeln('Resolved Configuration:');
        $output->writeln(print_r($config, true));

        return Command::SUCCESS;
    }
}
