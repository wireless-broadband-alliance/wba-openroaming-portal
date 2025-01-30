<?php

namespace App\Command;

use App\Service\SamlActiveProviderService;
use OneLogin\Saml2\Error;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:debug-active-saml',
    description: 'Debug Current Active SAML configuration.'
)]
class DebugActiveSamlCommand extends Command
{
    public function __construct(
        private readonly SamlActiveProviderService $service
    ) {
        parent::__construct();
    }

    /**
     * @throws Error
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->service->getActiveSamlProvider();
        $output->writeln('Resolved Configuration:');
        $output->writeln(print_r($config, true));

        return Command::SUCCESS;
    }
}
