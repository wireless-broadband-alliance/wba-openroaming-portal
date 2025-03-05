<?php

namespace App\Command;

use App\Service\SamlProviderResolverService;
use OneLogin\Saml2\Error;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'app:debug-active-saml',
    description: 'Debug a specific SAML configuration by name.'
)]
class DebugActiveSamlCommand extends Command
{
    public function __construct(
        private readonly SamlProviderResolverService $samlActiveProvider
    ) {
        parent::__construct();
    }

    /**
     * @throws Error
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Ask for the name
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question('Please enter the name of the SAML provider to debug: ');
        $name = $helper->ask($input, $output, $question);

        if (empty($name)) {
            $output->writeln('<error>The name cannot be empty. Exiting command.</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Fetching SAML configuration for provider: %s</info>', $name));

        try {
            $config = $this->samlActiveProvider->authSamlProviderByName($name);

            $output->writeln('Resolved Configuration:');
            $output->writeln(print_r($config, true));
        } catch (Error $e) {
            $output->writeln(sprintf('<error>Failed to fetch SAML configuration: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
