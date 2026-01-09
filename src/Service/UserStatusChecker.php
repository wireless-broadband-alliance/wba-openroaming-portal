<?php

namespace App\Service;

use App\Api\V1\BaseResponse as BaseResponseV1;
use App\Api\V2\BaseResponse as BaseResponseV2;
use App\Api\V3\BaseResponse as BaseResponseV3;
use App\Entity\DomainBlacklist;
use App\Entity\User;
use App\Enum\DomainMatchType;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\DomainBlacklistRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTimeInterface;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserStatusChecker
{
    public function __construct(
        private UserRepository $userRepository,
        private SettingRepository $settingRepository,
        private TranslatorInterface $translator,
        private DomainBlacklistRepository $domainBlacklistRepository,
    ) {
    }

    public function checkUserStatus(
        User $user
    ): BaseResponseV1|BaseResponseV2|BaseResponseV3|null {
        if (!$user->isVerified()) {
            return new BaseResponseV3(
                401,
                null,
                'User account is not verified.'
            );
        }

        if ($user->getBannedAt() instanceof DateTimeInterface) {
            return
                new BaseResponseV3(
                    403,
                    null,
                    'User account is banned from the system.'
                );
        }

        // Checks if the user has a "forgot_password_request", if yes, send an error with the authentication
        if ($this->userRepository->findOneBy(['id' => $user->getId(), 'forgot_password_request' => true])) {
            return
                new BaseResponseV3(
                    403,
                    null,
                    'Your request cannot be processed at this time due to a pending action.' .
                    ' If your account is active, re-login to complete the action.'
                );
        }
        return null;
    }

    public function portalAccountType(User $user): false|string
    {
        $userExternalAuths = $user->getUserExternalAuths();

        foreach ($userExternalAuths as $userExternalAuth) {
            if ($userExternalAuth->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
                if ($userExternalAuth->getProviderId() === UserProvider::EMAIL->value) {
                    return UserProvider::EMAIL->value;
                }

                if ($userExternalAuth->getProviderId() === UserProvider::PHONE_NUMBER->value) {
                    return UserProvider::PHONE_NUMBER->value;
                }
            }
        }
        return false;
    }

    public function isValidEmail(string $email, string $providerName): bool
    {
        // Determine which valid domains setting to use
        $settingName = match ($providerName) {
            UserProvider::MICROSOFT_ACCOUNT->value => SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value,
            UserProvider::GOOGLE_ACCOUNT->value => SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value,
            default => throw new RuntimeException(
                $this->translator->trans('invalidProviderName', [], 'UserStatusChecker')
            ),
        };

        $validDomainsSetting = $this->settingRepository->findOneBy(['name' => $settingName]);

        if ($validDomainsSetting === null) {
            throw new RuntimeException(
                $this->translator->trans('validDomainsNotFound', [], 'UserStatusChecker')
            );
        }

        // Extract domain from email
        if (!str_contains($email, '@')) {
            return false; // invalid email format
        }
        [, $domain] = explode('@', strtolower($email), 2);

        // Check whitelist (if defined)
        $validDomains = trim((string)$validDomainsSetting->getValue());
        if ($validDomains !== '') {
            $validDomainsList = array_map(trim(...), explode(',', $validDomains));
            if (!in_array($domain, $validDomainsList, true)) {
                return false;
            }
        }

        // Check blacklist
        foreach ($this->domainBlacklistRepository->findAll() as $domainDB) {
            $pattern = $domainDB->getPattern();
            $type = $domainDB->getType();

            if ($type === DomainMatchType::WILDCARD) {
                // Block everything
                return false;
            }

            if ($type === DomainMatchType::EXACT && $domain === $pattern) {
                return false;
            }

            if ($type === DomainMatchType::SUBDOMAIN &&
                ($domain === $pattern || str_ends_with($domain, '.' . $pattern))) {
                return false;
            }
        }

        // Passed both whitelist and blacklist
        return true;
    }
}
