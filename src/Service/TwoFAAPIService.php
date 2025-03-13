<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\TwoFAType;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\SettingRepository;

readonly class TwoFAAPIService
{
    public function __construct(
        private SettingRepository $settingRepository,
        private TwoFAService $twoFAService,
    ) {
    }

    public function twoFAEnforcementChecker(User $user, string $endpointName): array
    {
        $status2FA = $this->settingRepository->findOneBy(['name' => 'TWO_FACTOR_AUTH_STATUS']);

        // If $status2FA is null or not found, return an error message
        if (!$status2FA) {
            return [
                'success' => false,
                'message' => 'Missing required configuration setting: TWO_FACTOR_AUTH_STATUS',
            ];
        }

        // Get the 2FA enforcement status value
        $twoFAValue = $status2FA->getValue();

        // User 2FA configuration details
        $user2FACurrentState = $this->twoFAUserConfiguration($user);

        // EARLY EXIT: If the enforcement is NOT_ENFORCED
        if ($twoFAValue === TwoFAType::NOT_ENFORCED->value) {
            if ($user2FACurrentState['isActive'] === true) {
                // If 2FA is active, return details and disallow flow
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Two-Factor Authentication is active and configured as: %s.',
                        $user2FACurrentState['type']
                    ),
                    'details' => $user2FACurrentState,
                ];
            }

            // If 2FA is not active, allow flow to continue
            return [
                'success' => true,
            ];
        }

        // ENFORCED CASE: Handle endpoint-specific enforcement logic
        if ($endpointName === 'api_auth_local') {
            if ($twoFAValue === TwoFAType::ENFORCED_FOR_LOCAL->value) {
                if ($user2FACurrentState['isActive'] === true) {
                    return [
                        'success' => true,
                        'message' => sprintf(
                            'Two-Factor Authentication is active and ENFORCED_FOR_LOCAL as: %s.',
                            $user2FACurrentState['type']
                        ),
                    ];
                }

                // If user does not have 2FA active, return an enforcement error
                return [
                    'success' => false,
                    'message' => 'Two-Factor Authentication is ENFORCED FOR PORTAL account only.',
                ];
            }

            if ($twoFAValue === TwoFAType::ENFORCED_FOR_ALL->value) {
                if ($user2FACurrentState['isActive'] === true) {
                    return [
                        'success' => true,
                        'message' => sprintf(
                            'Two-Factor Authentication is active and ENFORCED_FOR_ALL as: %s.',
                            $user2FACurrentState['type']
                        ),
                    ];
                }

                // If user does not have 2FA active, return an enforcement error
                return [
                    'success' => false,
                    'message' => 'Two-Factor Authentication is ENFORCED_FOR_ALL but is not active for the user.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Unhandled Two-Factor Authentication status for the local endpoint.',
            ];
        }

        // Handle external providers like Google, Microsoft, SAML
        if ($twoFAValue === TwoFAType::ENFORCED_FOR_LOCAL->value) {
            if ($user2FACurrentState['isActive'] === true) {
                return [
                    'success' => false,
                    'message' => 'Two-Factor Authentication is active and configured for LOCAL, but accessed '
                        . 'from an external endpoint as: ' . $user2FACurrentState['type'] . '.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Two-Factor Authentication is ENFORCED_FOR_LOCAL but accessed 
                through an external endpoint.',
            ];
        }

        if ($twoFAValue === TwoFAType::ENFORCED_FOR_ALL->value) {
            if ($user2FACurrentState['isActive'] === true) {
                return [
                    'success' => true,
                    'message' => sprintf(
                        'Two-Factor Authentication is active and configured as: %s.',
                        $user2FACurrentState['type']
                    ),
                ];
            }

            return [
                'success' => false,
                'message' => 'Two-Factor Authentication is ENFORCED_FOR_ALL but is not active for the user.',
            ];
        }

        // Fallback for unexpected cases
        return [
            'success' => false,
            'message' => 'Unhandled Two-Factor Authentication status in the enforcement logic.',
        ];
    }

    private function twoFAUserConfiguration(User $user): array
    {
        // Check if the user's 2FA is active
        if ($this->twoFAService->twoFAisActive($user)) {
            // Handle different user 2FA types
            if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::APP->value) {
                return [
                    'type' => UserTwoFactorAuthenticationStatus::APP->value,
                    'isActive' => true,
                    'secret' => $user->getTwoFASecret(),
                    'otpCodes' => $user->getOTPcodes()
                ];
            }

            if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::SMS->value) {
                return [
                    'type' => UserTwoFactorAuthenticationStatus::SMS->value,
                    'isActive' => true,
                    'code' => $user->getTwoFAcode(),
                    'otpCodes' => $user->getOTPcodes()
                ];
            }

            if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::EMAIL->value) {
                return [
                    'type' => UserTwoFactorAuthenticationStatus::EMAIL->value,
                    'isActive' => true,
                    'code' => $user->getTwoFAcode(),
                    'otpCodes' => $user->getOTPcodes()
                ];
            }
        }

        // Check if the user's 2FA is NOT active
        if ($user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value) {
            return [
                'message' => 'User does not have Two-Factor Authentication fully configured but it\'s inactive.',
                'isActive' => false,
            ];
        }

        // User does not have any 2FA configured
        return [
            'message' => 'User does not have Two-Factor Authentication  configured.',
            'isActive' => false,
        ];
    }
}
