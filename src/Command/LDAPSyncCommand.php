<?php

namespace App\Command;

use App\Entity\LdapCredential;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\ProfileManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ldap:sync',
    description: 'Sync user account status with LDAP',
)]
class LDAPSyncCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProfileManager $profileManager,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly SamlProviderRepository $samlProviderRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $samlProvider = $this->samlProviderRepository->findOneBy([
            'isActive' => true,
            'isLDAPActive' => true,
            'deletedAt' => null
        ]);

        if (!$samlProvider) {
            $io->writeln(
                '<error>No active SAML Provider is currently configured. ' .
                ' Please ensure a SAML Provider is set up and associated with an active LDAP Credential.</error>'
            );
            return Command::FAILURE;
        }

        // Retrieve the LDAP Credential associated with the active SAML Provider
        $ldapCredential = $samlProvider->getLdapCredential();

        if (!$ldapCredential) {
            $io->writeln(
                sprintf(
                    '<error>No LDAP Credential is associated with the active SAML Provider (%s).</error>',
                    $samlProvider->getName()
                )
            );
            return Command::FAILURE;
        }


        $ldapEnabledUsers = $this->userRepository->findLDAPEnabledUsers();
        $io->writeln('Found ' . count($ldapEnabledUsers) . ' LDAP enabled users');
        foreach ($ldapEnabledUsers as $user) {
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);

            foreach ($userExternalAuths as $externalAuth) {
                if ($externalAuth->getProvider() === UserProvider::SAML->value) {
                    $providerId = $externalAuth->getProviderId();
                    $io->writeln('Syncing ' . $providerId . ' with LDAP');

                    $ldapUser = $this->fetchUserFromLDAP($providerId, $ldapCredential);
                    if (is_null($ldapUser)) {
                        $io->writeln('User ' . $providerId . ' not found in LDAP, disabling');
                        $this->profileManager->disableProfiles(
                            $user,
                            UserRadiusProfileRevokeReason::LDAP_UNKNOWN_USER->value
                        );
                        continue;
                    }

                    $userAccountControl = $ldapUser['userAccountControl'][0];
                    $passwordExpired = ($userAccountControl & 0x800000) == 0x800000;
                    $userLocked = ($userAccountControl & 0x000002) == 0x000002;

                    if ($userLocked) {
                        $io->writeln('User ' . $providerId . ' is locked in LDAP, disabling');
                        $this->profileManager->disableProfiles(
                            $user,
                            UserRadiusProfileRevokeReason::LDAP_USER_LOCKED->value
                        );
                    } elseif ($passwordExpired || $ldapUser["pwdLastSet"][0] === "0") {
                        $io->writeln('User ' . $providerId . ' has an expired password in LDAP, disabling');
                        $this->profileManager->disableProfiles(
                            $user,
                            UserRadiusProfileRevokeReason::LDAP_USER_PASSWORD_EXPIRED->value
                        );
                    } else {
                        $io->writeln('User ' . $providerId . ' is enabled in LDAP, enabling');
                        $this->enableProfiles($user);
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    private function fetchUserFromLDAP(string $identifier, LdapCredential $ldapCredential)
    {
        $ldapServer = $ldapCredential->getServer();
        $ldapUsername = $ldapCredential->getBindUserDn();
        $ldapPassword = $ldapCredential->getBindUserPassword();
        $ldapConnection = ldap_connect($ldapServer) or die("Could not connect to LDAP server.");
        ldap_set_option($ldapConnection, LDAP_OPT_DEREF, LDAP_DEREF_ALWAYS);
        ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, 0);
        if (!ldap_bind($ldapConnection, $ldapUsername, $ldapPassword)) {
            die("Could not bind to LDAP server.");
        }
        $searchFilter = str_replace(
            "@ID",
            $identifier,
            $ldapCredential->getSearchFilter()
        );
        $searchBaseDN = $ldapCredential->getSearchBaseDn();
        $searchResult = ldap_search(
            $ldapConnection,
            $searchBaseDN,
            $searchFilter,
        );

        $entry = ldap_first_entry($ldapConnection, $searchResult);
        if (!$entry) {
            ldap_unbind($ldapConnection);
            return null;
        }
        $attrs = ldap_get_attributes($ldapConnection, $entry);
        ldap_unbind($ldapConnection);
        return $attrs;
    }

    private function enableProfiles($user): void
    {
        $this->profileManager->enableProfiles($user);
    }
}
