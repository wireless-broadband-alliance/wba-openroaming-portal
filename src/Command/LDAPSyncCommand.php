<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Exception\EncryptionException;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\EncryptionService;
use App\Service\ProfileManager;
use LDAP\Result;
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
        private readonly SettingRepository $settingRepository,
        private readonly ProfileManager $profileManager,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly EncryptionService $encryptionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $enabledSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::SYNC_LDAP_ENABLED->value
        ]);

        if (!$enabledSetting || $enabledSetting->getValue() === 'false') {
            $io->writeln('LDAP sync is disabled');
            return Command::SUCCESS;
        }

        $ldapEnabledUsers = $this->userRepository->findLDAPEnabledUsers();
        $io->writeln('Found ' . count($ldapEnabledUsers) . ' LDAP enabled users');

        foreach ($ldapEnabledUsers as $user) {
            $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);

            foreach ($userExternalAuths as $externalAuth) {
                if ($externalAuth->getProvider() === UserProvider::SAML->value) {
                    $providerId = $externalAuth->getProviderId();
                    $io->writeln('Syncing ' . $providerId . ' with LDAP');

                    $ldapUser = $this->fetchUserFromLDAP($providerId);
                    if (is_null($ldapUser)) {
                        $io->writeln('User ' . $providerId . ' not found in LDAP, disabling');
                        $this->profileManager->disableProfiles(
                            $user,
                            UserRadiusProfileRevokeReason::LDAP_UNKNOWN_USER->value
                        );
                        continue;
                    }

                    $userAccountControl = $ldapUser['userAccountControl'][0];
                    $passwordExpired = ($userAccountControl & 0x800000) === 0x800000;
                    $userLocked = ($userAccountControl & 0x000002) === 0x000002;

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

    /**
     * @return array<int|string, mixed>|null
     */
    private function fetchUserFromLDAP(string $identifier): ?array
    {
        // Fetch and decrypt settings
        $ldapServer = $this->safeDecrypt(
            $this->settingRepository->findOneBy(['name' => SettingName::SYNC_LDAP_SERVER->value])?->getValue()
        );
        $ldapUsername = $this->safeDecrypt(
            $this->settingRepository->findOneBy(['name' => SettingName::SYNC_LDAP_BIND_USER_DN->value])?->getValue()
        );
        $ldapPassword = $this->safeDecrypt(
            $this->settingRepository->
            findOneBy(['name' => SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value])?->getValue()
        );
        $searchBaseDN = $this->safeDecrypt(
            $this->settingRepository->findOneBy(['name' => SettingName::SYNC_LDAP_SEARCH_BASE_DN->value])?->getValue()
        );

        if (!$ldapServer || !$ldapUsername || !$ldapPassword || !$searchBaseDN) {
            return null;
        }

        $ldapConnection = @ldap_connect($ldapServer);
        if (!$ldapConnection) {
            return null;
        }

        ldap_set_option($ldapConnection, LDAP_OPT_DEREF, LDAP_DEREF_ALWAYS);
        ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConnection, LDAP_OPT_REFERRALS, 0);

        if (!@ldap_bind($ldapConnection, $ldapUsername, $ldapPassword)) {
            return null;
        }

        $searchFilterSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::SYNC_LDAP_SEARCH_FILTER->value
        ]);

        if (!$searchFilterSetting) {
            ldap_unbind($ldapConnection);
            return null;
        }

        $searchFilter = str_replace("@ID", $identifier, $searchFilterSetting->getValue());

        $searchResult = ldap_search($ldapConnection, $searchBaseDN, $searchFilter);
        if ($searchResult === false) {
            ldap_unbind($ldapConnection);
            return null;
        }

        /** @var Result $searchResult */
        $entry = ldap_first_entry($ldapConnection, $searchResult);
        if (!$entry) {
            ldap_unbind($ldapConnection);
            return null;
        }

        $attrs = ldap_get_attributes($ldapConnection, $entry);
        ldap_unbind($ldapConnection);

        return $attrs;
    }

    private function enableProfiles(User $user): void
    {
        $this->profileManager->enableProfiles($user);
    }

    /**
     * Safely decrypt values while remaining backwards-compatible with unencrypted legacy settings.
     */
    private function safeDecrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return $this->encryptionService->decrypt($value);
        } catch (EncryptionException) {
            // Fall back to raw string if it was stored in plaintext prior to encryption implementation
            return $value;
        }
    }
}
