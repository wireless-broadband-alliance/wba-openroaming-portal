<?php

namespace App\Command;

use App\Entity\UserRadiusProfile;
use App\Enum\UserRadiusProfileStatus;
use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRadiusProfileRepository;
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

    public function __construct(
        UserRepository    $userRepository,
        SettingRepository $settingRepository,
        ProfileManager    $profileManager
    )
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->profileManager = $profileManager;

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
            $io->writeln('Syncing ' . $user->saml_identifier . ' with LDAP');
            $ldapUser = $this->fetchUserFromLDAP($user->saml_identifier);
            if (!$ldapUser) {
                $io->writeln('User ' . $user->saml_identifier . ' not found in LDAP, disabling');
                $this->disableProfiles($user);
                continue;
            }
            $userAccountControl = $ldapUser['useraccountcontrol'][0];
            $passwordExpired = ($userAccountControl & 0x800000) == 0x800000;
            $userLocked = ($userAccountControl & 0x000002) == 0x000002;

            if ($userLocked) {
                $io->writeln('User ' . $user->saml_identifier . ' is locked in LDAP, disabling');
                $this->disableProfiles($user);
            } else if ($passwordExpired) {
                $io->writeln('User ' . $user->saml_identifier . ' has an expired password in LDAP, disabling');
                $this->disableProfiles($user);
            } else {
                $io->writeln('User ' . $user->saml_identifier . ' is enabled in LDAP, enabling');
                $this->enableProfiles($user);
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
        ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, 1);
        ldap_bind($ldapConnection, $ldapUsername, $ldapPassword) or die("Could not bind to LDAP server.");
        $searchFilter = str_replace("@ID", $identifier, $this->settingRepository->findOneBy(['name' => 'SYNC_LDAP_SEARCH_FILTER'])->getValue());
        $searchBaseDN = $this->settingRepository->findOneBy(['name' => 'SYNC_LDAP_SEARCH_BASE_DN'])->getValue();
        $searchResult = ldap_search($ldapConnection, $searchBaseDN, $searchFilter);

        ldap_get_option($ldapConnection, LDAP_OPT_REFERRALS, $referrals);
        ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, $referrals);

        $searchEntries = ldap_get_entries($ldapConnection, $searchResult);
        ldap_unbind($ldapConnection);

        if ($searchEntries['count'] === 1) {
            return $searchEntries[0];
        }

        return null;
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
