<?php

namespace App\Service;

use App\Api\BaseResponseInterface;
use App\Api\V3\BaseResponse as BaseResponseV3;
use App\Api\V4\BaseResponse as BaseResponseV4;
use App\Entity\User;
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

    /**
     * Returns an error response when the account can't use the API (not verified, banned or with a
     * pending forgot-password request), or null when it can.
     *
     * The response is built with the BaseResponse class of the calling API version, so V4 controllers
     * get a V4 response. Callers should check the result with `instanceof BaseResponseInterface`.
     *
     * @param class-string<BaseResponseV3|BaseResponseV4> $responseClass
     */
    public function checkUserStatus(
        User $user,
        string $responseClass = BaseResponseV3::class
    ): ?BaseResponseInterface {
        if (!$user->isVerified()) {
            return new $responseClass(
                401,
                null,
                'User account is not verified.'
            );
        }

        if ($user->getBannedAt() instanceof DateTimeInterface) {
            return
                new $responseClass(
                    403,
                    null,
                    'User account is banned from the system.'
                );
        }

        // Checks if the user has a "forgot_password_request", if yes, send an error with the authentication
        if ($this->userRepository->findOneBy(['id' => $user->getId(), 'forgot_password_request' => true])) {
            return
                new $responseClass(
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

    public function isValidEmail(string $email, ?string $providerName = null): bool
    {
        // Extract domain from email
        if (!str_contains($email, '@')) {
            return false; // invalid email
        }

        [, $domain] = explode('@', strtolower($email), 2);

        // Check whitelist only if providerName is provided
        if ($providerName !== null) {
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

            $validDomainsList = array_filter(
                array_map(trim(...), explode(',', (string)$validDomainsSetting->getValue()))
            );

            if ($validDomainsList !== [] && !in_array($domain, $validDomainsList, true)) {
                return false;
            }
        }
        // Always check the blacklist
        return !$this->domainBlacklistRepository->isDomainBlacklisted($domain);
    }
}
