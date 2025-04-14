<?php

namespace App\Service;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTimeInterface;
use RuntimeException;

readonly class UserStatusChecker
{
    public function __construct(
        private UserRepository $userRepository,
        private SettingRepository $settingRepository
    ) {
    }

    public function checkUserStatus(User $user): ?BaseResponse
    {
        if (!$user->isVerified()) {
            return new BaseResponse(
                401,
                null,
                'User account is not verified.'
            );
        }

        if ($user->getBannedAt() instanceof DateTimeInterface) {
            return
                new BaseResponse(
                    403,
                    null,
                    'User account is banned from the system.'
                );
        }

        // Checks if the user has a "forgot_password_request", if yes, send an error with the authentication
        if ($this->userRepository->findOneBy(['id' => $user->getId(), 'forgot_password_request' => true])) {
            return
                new BaseResponse(
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
        if ($providerName === UserProvider::MICROSOFT_ACCOUNT->value) {
            // Retrieve the valid domains setting from the database
            $validDomainsSetting = $this->settingRepository->findOneBy(['name' => 'VALID_DOMAINS_MICROSOFT_LOGIN']);
        } elseif ($providerName === UserProvider::GOOGLE_ACCOUNT->value) {
            // Retrieve the valid domains setting from the database
            $validDomainsSetting = $this->settingRepository->findOneBy(['name' => 'VALID_DOMAINS_GOOGLE_LOGIN']);
        } else {
            // If providerName doesn't match any valid providers, throw an exception
            throw new RuntimeException('Invalid provider name provided.');
        }

        // Throw an exception if the setting is not found
        if ($validDomainsSetting === null) {
            throw new RuntimeException("Valid domains setting not found for the provider: $providerName");
        }

        // If the valid domains setting is empty, allow all domains
        $validDomains = $validDomainsSetting->getValue();
        if (empty($validDomains)) {
            return true;
        }

        // Split the valid domains into an array and trim whitespace
        $validDomains = explode(',', $validDomains);
        $validDomains = array_map('trim', $validDomains);

        // Extract the domain from the email
        $emailParts = explode('@', $email);
        $domain = end($emailParts);

        // Check if the domain is in the list of valid domains
        return in_array($domain, $validDomains, true);
    }
}
