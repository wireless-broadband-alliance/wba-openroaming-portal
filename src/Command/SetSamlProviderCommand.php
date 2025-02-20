<?php

namespace App\Command;

use App\Entity\LdapCredential;
use App\Entity\SamlProvider;
use App\Entity\UserExternalAuth;
use App\Repository\LdapCredentialRepository;
use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use App\Service\SamlProviderValidator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
        private readonly ParameterBagInterface $parameterBag,
        private readonly SamlProviderRepository $samlProviderRepository,
        private readonly SamlProviderValidator $samlProviderValidator,
        private readonly SettingRepository $settingRepository,
        private readonly LdapCredentialRepository $ldapCredentialRepository,
    ) {
        parent::__construct();
    }

    /**
     * @throws \JsonException
     */
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

        $idpEntityId = $this->parameterBag->get('app.saml_idp_entity_id');
        $idpSsoUrl = $this->parameterBag->get('app.saml_idp_sso_url');
        $idpX509Cert = $this->parameterBag->get('app.saml_idp_x509_cert');
        $spEntityId = $this->parameterBag->get('app.saml_sp_entity_id');
        $spAcsUrl = $this->parameterBag->get('app.saml_sp_acs_url');

        // Validate that required parameters exist
        if (empty($idpEntityId) || empty($idpSsoUrl) || empty($idpX509Cert) || empty($spEntityId) || empty($spAcsUrl)) {
            $output->writeln(
                '<error>One or more required SAML parameters are missing. Check your environment configuration.</error>'
            );

            return self::FAILURE;
        }

        // Use SamlProviderValidator to validate duplicate entries based on all parameters
        $duplicateField = $this->samlProviderValidator->checkDuplicateSamlProvider(
            $name,
            $idpEntityId,
            $idpSsoUrl,
            $spEntityId,
            $spAcsUrl
        );
        if ($duplicateField) {
            $output->writeln(
                sprintf(
                    '<error>A SAML Provider with the same %s already exists in the system!</error>',
                    $duplicateField
                )
            );
            return self::FAILURE;
        }
        $checkIdpEntityId = $this->samlProviderValidator->validateJsonUrlSamlProvider($idpEntityId);
        if ($checkIdpEntityId) {
            $output->writeln(
                sprintf(
                    '<error>Failed to validate the SAML Provider URL (%s): %s</error>',
                    $idpSsoUrl,
                    $checkIdpEntityId
                )
            );
            return self::FAILURE;
        }
        $checkIdpX509Cert = $this->samlProviderValidator->validateCertificate($idpX509Cert);
        if ($checkIdpX509Cert) {
            $output->writeln(
                sprintf(
                    '<error>Failed to validate the SAML Provider Certificate: %s</error>',
                    $checkIdpX509Cert
                )
            );
            return self::FAILURE;
        }
        $checkSpEntityId = $this->samlProviderValidator->validateSamlMetadata($spEntityId);
        if ($checkSpEntityId) {
            $output->writeln(
                sprintf(
                    '<error>Failed to validate the SAML Provider Metadata (%s): %s</error>',
                    $spEntityId,
                    $checkSpEntityId
                )
            );
            return self::FAILURE;
        }

        try {
            // Try to create and persist the SAML Provider
            $this->createAndPersistSamlProvider(
                $name,
                $idpEntityId,
                $idpSsoUrl,
                $idpX509Cert,
                $spEntityId,
                $spAcsUrl
            );
            $output->writeln('<info>SAML Provider data has been set!</info>');

            try {
                // Fetch LDAP configuration from SettingRepository
                $server = $this->settingRepository->findOneBy([
                    'name' => 'SYNC_LDAP_SERVER'
                ])?->getValue();
                $bindUserDn = $this->settingRepository->findOneBy([
                    'name' => 'SYNC_LDAP_BIND_USER_DN'
                ])?->getValue();
                $bindUserPassword = $this->settingRepository->findOneBy([
                    'name' => 'SYNC_LDAP_BIND_USER_PASSWORD'
                ])?->getValue();
                $searchBaseDn = $this->settingRepository->findOneBy([
                    'name' => 'SYNC_LDAP_SEARCH_BASE_DN'
                ])?->getValue();
                $searchFilter = $this->settingRepository->findOneBy([
                    'name' => 'SYNC_LDAP_SEARCH_FILTER'
                ])?->getValue();

                // Try to create and persist the LDAP Credential
                $this->createAndPersistLdapCredential(
                    $server,
                    $bindUserDn,
                    $bindUserPassword,
                    $searchBaseDn,
                    $searchFilter,
                    $output
                );
                $output->writeln('<info>LDAP Credential has been created successfully!</info>');
            } catch (Exception $ldapException) {
                // Handle LDAP creation failure
                $output->writeln(
                    '<error>Failed to create LDAP Credential: ' . $ldapException->getMessage() . '</error>'
                );
                return self::FAILURE;
            }

            return self::SUCCESS; // Both operations succeeded
        } catch (Exception $samlException) {
            // Handle SAML Provider creation failure
            $output->writeln(
                '<error>Failed to create SAML Provider: ' . $samlException->getMessage() . '</error>'
            );
            return self::FAILURE;
        }
    }

    /**
     * @throws Exception
     */
    private function createAndPersistSamlProvider(
        string $name,
        string $idpEntityId,
        string $idpSsoUrl,
        string $idpX509Cert,
        string $spEntityId,
        string $spAcsUrl
    ): void {
        $this->entityManager->beginTransaction();

        try {
            // Ensure only 1 provider can be active:
            // Find the currently active provider and deactivate it
            $activeProvider = $this->samlProviderRepository->findOneBy(['isActive' => true, 'deletedAt' => null]);
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
                    $userExternalAuth->setSamlProvider($samlProvider);
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

    /**
     * Attempts to insert or update LDAP credentials without affecting the SAML Provider insertion.
     */
    private function createAndPersistLdapCredential(
        string $server,
        ?string $bindUserDn,
        ?string $bindUserPassword,
        ?string $searchBaseDn,
        ?string $searchFilter,
        OutputInterface $output
    ): void {
        // Abort if the server is not configured
        if ($server === '' || $server === '0') {
            $output->writeln(
                '<comment>LDAP server configuration is missing or empty. '
                . 'Skipping LDAP credential creation.</comment>'
            );
            return;
        }

        try {
            $activeProvider = $this->samlProviderRepository->findOneBy(['isActive' => true, 'deletedAt' => null]);
            if (!$activeProvider) {
                $output->writeln(
                    '<comment>Active LDAP server configuration is missing or incomplete. '
                    . 'LDAP credential creation has been skipped.</comment>'
                );
                return;
            }
            // Check if there's an existing LDAPCredential linked to this SAML Provider
            $ldapCredential = $this->ldapCredentialRepository->findOneBy(['samlProvider' => $activeProvider]);

            if ($ldapCredential) {
                // Overwrite the existing LDAPCredential values
                $ldapCredential->setServer($server)
                    ->setBindUserDn($bindUserDn)
                    ->setBindUserPassword($bindUserPassword)
                    ->setSearchBaseDn($searchBaseDn)
                    ->setSearchFilter($searchFilter)
                    ->setIsLDAPActive(true)
                    ->setUpdatedAt(new DateTime());

                $output->writeln('<comment>Existing LDAP Credential updated for the SAML Provider.</comment>');
            } else {
                // Create a new LDAPCredential
                $ldapCredential = new LdapCredential();
                $ldapCredential->setServer($server)
                    ->setBindUserDn($bindUserDn)
                    ->setBindUserPassword($bindUserPassword)
                    ->setSearchBaseDn($searchBaseDn)
                    ->setSearchFilter($searchFilter)
                    ->setIsLDAPActive(true)
                    ->setSamlProvider($activeProvider)
                    ->setUpdatedAt(new DateTime());

                $output->writeln('<comment>New LDAP Credential generated for the SAML Provider.</comment>');
            }

            $this->entityManager->persist($ldapCredential);
            $this->entityManager->flush();
        } catch (Exception $e) {
            $output->writeln(
                sprintf(
                    '<error>Failed to insert or update LDAP Credential: %s</error>',
                    $e->getMessage()
                )
            );
        }
    }
}
