<?php

namespace App\Command;

use App\Service\SamlActiveProviderService;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugSamlConfigCommand extends Command
{
    protected static $defaultName = 'app:debug-saml-config';

    private SamlActiveProviderService $service;

    public function __construct(SamlActiveProviderService $service)
    {
        $this->service = $service;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Debug SAML configuration resolution.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->service->getActiveSamlProvider();
        $output->writeln('Resolved Configuration:');
        $output->writeln(print_r($config, true));

        return Command::SUCCESS;
    }
}
