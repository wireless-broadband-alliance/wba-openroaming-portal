<?php

namespace App\Command;

use App\Entity\SamlProvider;
use App\Entity\UserExternalAuth;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:set-saml-provider',
    description: 'Sets SAML Provider data from the ENV',
)]
class SetSamlProviderCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
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

        // Validate camelCase
        if (!preg_match('/^[a-z]+([A-Z][a-z]*)*$/', (string)$name)) {
            $output->writeln('<error>Name must be in camelCase format (e.g., mySamlProvider).</error>');

            return self::FAILURE;
        }

        try {
            $this->createAndPersistSamlProvider($name);
            $output->writeln('SAML Provider data has been set!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }
    }

    /**
     * @throws Exception
     */
    private function createAndPersistSamlProvider(string $name): void
    {
        $idpEntityId = $this->parameterBag->get('app.saml_idp_entity_id');
        $idpSsoUrl = $this->parameterBag->get('app.saml_idp_sso_url');
        $idpX509Cert = $this->parameterBag->get('app.saml_idp_x509_cert');
        $spEntityId = $this->parameterBag->get('app.saml_sp_entity_id');
        $spAcsUrl = $this->parameterBag->get('app.saml_sp_acs_url');

        // Validate required SAML fields
        if (empty($idpEntityId) || empty($idpSsoUrl) || empty($idpX509Cert) || empty($spEntityId) || empty($spAcsUrl)) {
            throw new InvalidArgumentException('One or more SAML configuration values are missing!');
        }

        $this->entityManager->beginTransaction(); // Begin a database transaction for atomicity

        try {
            // Ensure only 1 provider can be active:
            // Find the currently active provider and deactivate it
            $activeProvider = $this->entityManager->getRepository(SamlProvider::class)->findOneBy(['isActive' => true]);
            if ($activeProvider) {
                $activeProvider->setActive(false);
                $this->entityManager->persist($activeProvider);
            }

            // Create and persist the new provider
            $samlProvider = new SamlProvider();
            $samlProvider->setName($name);
            $samlProvider->setIdpEntityId($idpEntityId);
            $samlProvider->setIdpSsoUrl($idpSsoUrl);
            $samlProvider->setIdpX509Cert($idpX509Cert);
            $samlProvider->setSpEntityId($spEntityId);
            $samlProvider->setSpAcsUrl($spAcsUrl);
            $samlProvider->setActive(true);
            $samlProvider->setCreatedAt(new DateTime());
            $samlProvider->setUpdatedAt(new DateTime());

            $this->entityManager->persist($samlProvider);
            $this->entityManager->flush();

            // Reassign UserExternalAuth entities associated with the old provider to the new provider
            if ($activeProvider) {
                $userExternalAuths = $this->entityManager->getRepository(UserExternalAuth::class)
                    ->findBy(['samlProvider' => $activeProvider]); // Find all users linked to the old provider

                foreach ($userExternalAuths as $userExternalAuth) {
                    $userExternalAuth->setSamlProvider($samlProvider); // Link them to the new provider
                    $this->entityManager->persist($userExternalAuth);
                }

                $this->entityManager->flush();
            }

            $this->entityManager->commit();
        } catch (Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
