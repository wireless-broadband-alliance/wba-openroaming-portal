<?php

namespace App\Command;

use App\Entity\SamlProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SetSamlProviderCommand extends Command
{
    protected static $defaultName = 'app:set-saml-provider';

    private EntityManagerInterface $entityManager;
    private ParameterBagInterface $parameterBag;

    public function __construct(EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag)
    {
        $this->entityManager = $entityManager;
        $this->parameterBag = $parameterBag;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sets SAML Provider data from the ENV')
            ->setHelp('This command allows to set SAML Provider data from the ENV');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question('Please enter the name of the provider: ');
        $name = $helper->ask($input, $output, $question);

        if (empty($name)) {
            $output->writeln('<error>Name cannot be empty!</error>');
            return self::FAILURE;
        }

        try {
            $this->createAndPersistSamlProvider($name);
            $output->writeln('SAML Provider data has been set!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return self::FAILURE;
        }
    }

    private function createAndPersistSamlProvider(string $name): void
    {
        $samlProvider = new SamlProvider();

        $samlProvider->setName($name);
        $samlProvider->setIdpEntityId($this->parameterBag->get('app.saml_idp_entity_id'));
        $samlProvider->setIdpSsoUrl($this->parameterBag->get('app.saml_idp_sso_url'));
        $samlProvider->setIdpX509Cert($this->parameterBag->get('app.saml_idp_x509_cert'));
        $samlProvider->setSpEntityId($this->parameterBag->get('app.saml_sp_entity_id'));
        $samlProvider->setSpAcsUrl($this->parameterBag->get('app.saml_sp_acs_url'));
        $samlProvider->setActive(true);

        $this->entityManager->persist($samlProvider);
        $this->entityManager->flush();
    }
}
