<?php

namespace App\Command;

use App\Enum\UserProvider;
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
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private ProfileManager $profileManager;
    private UserExternalAuthRepository $userExternalAuthRepository;

    public function __construct(
        UserRepository $userRepository,
        SettingRepository $settingRepository,
        ProfileManager $profileManager,
        UserExternalAuthRepository $userExternalAuthRepository
    ) {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->profileManager = $profileManager;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->settingRepository->findOneBy(['name' => 'SYNC_LDAP_ENABLED'])->getValue() === 'false') {
            $io->writeln('LDAP sync is disabled');
            return Command::SUCCESS;
        }
        $ldapEnabledUsers = $this->userRepository->findLDAPEnabledUsers();
        $io->writeln('Found ' . count($ldapEnabledUsers) . ' LDAP enabled users');
        foreach ($ldapEnabledUsers as $user) {
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);

            foreach ($userExternalAuths as $externalAuth) {
                if ($externalAuth->getProvider() === UserProvider::SAML) {
                    $providerId = $externalAuth->getProviderId();
                    $io->writeln('Syncing ' . $providerId . ' with LDAP');

                    $ldapUser = $this->fetchUserFromLDAP($providerId);
                    if (is_null($ldapUser)) {
                        $io->writeln('User ' . $providerId . ' not found in LDAP, disabling');
                        $this->disableProfiles($user);
                        continue;
                    }

                    $userAccountControl = $ldapUser['userAccountControl'][0];
                    $passwordExpired = ($userAccountControl & 0x800000) == 0x800000;
                    $userLocked = ($userAccountControl & 0x000002) == 0x000002;

                    if ($userLocked) {
                        $io->writeln('User ' . $providerId . ' is locked in LDAP, disabling');
                        $this->disableProfiles($user);
                    } elseif ($passwordExpired || $ldapUser["pwdLastSet"][0] === "0") {
                        $io->writeln('User ' . $providerId . ' has an expired password in LDAP, disabling');
                        $this->disableProfiles($user);
                    } else {
                        $io->writeln('User ' . $providerId . ' is enabled in LDAP, enabling');
                        $this->enableProfiles($user);
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    private function fetchUserFromLDAP(string $identifier)
    {
        $ldapServer = $this->settingRepository->findOneBy(['name' => 'SYNC_LDAP_SERVER'])->getValue();
        $ldapUsername = $this->settingRepository->findOneBy(['name' => 'SYNC_LDAP_BIND_USER_DN'])->getValue();
        $ldapPassword = $this->settingRepository->findOneBy(['name' => 'SYNC_LDAP_BIND_USER_PASSWORD'])->getValue();
        $ldapConnection = ldap_connect($ldapServer) or die("Could not connect to LDAP server.");
        ldap_set_option($ldapConnection, LDAP_OPT_DEREF, LDAP_DEREF_ALWAYS);
        ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, 0);
        ldap_bind($ldapConnection, $ldapUsername, $ldapPassword) or die("Could not bind to LDAP server.");
        $searchFilter = str_replace(
            "@ID",
            $identifier,
            $this->settingRepository->findOneBy(['name' => 'SYNC_LDAP_SEARCH_FILTER'])->getValue()
        );
        $searchBaseDN = $this->settingRepository->findOneBy(['name' => 'SYNC_LDAP_SEARCH_BASE_DN'])->getValue();
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

    private function disableProfiles($user): void
    {
        $this->profileManager->disableProfiles($user);
    }

    private function enableProfiles($user): void
    {
        $this->profileManager->enableProfiles($user);
    }
}
