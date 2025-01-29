<?php

namespace App\Command;

use App\Service\SamlActiveProviderService;
use OneLogin\Saml2\Error;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugActiveSamlCommand extends Command
{
    protected static $defaultName = 'app:debug-active-saml';


    public function __construct(
        private readonly SamlActiveProviderService $service
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Debug Current Active SAML configuration.');
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
