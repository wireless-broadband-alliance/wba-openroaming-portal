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
                    'message' => 'Two-Factor Authentication is active for this account.' .
                        ' Please ensure you provide the correct authentication code.',
                    'details' => $user2FACurrentState,
                ];
            }

            // If 2FA is not active, allow flow to continue
            return [
                'success' => true,
                '2FAType' => $twoFAValue
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
                        '2FAType' => $twoFAValue
                    ];
                }

                // If user does not have 2FA active, return an enforcement error
                return [
                    'success' => false,
                    'message' => 'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
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
                        '2FAType' => $twoFAValue
                    ];
                }

                // If user does not have 2FA active, return an enforcement error
                return [
                    'success' => false,
                    'message' => 'Two-Factor Authentication it\'s required for authentication on the portal.' .
                        'Please visit' . $_SERVER['HTTP_HOST'] . ' to set up 2FA and secure your account.',
                    '2FAType' => $twoFAValue,
                ];
            }

            return [
                'success' => false,
                'missing_2fa_setting' => true,
                'message' => 'Unhandled Two-Factor Authentication status for the local endpoint.',
            ];
        }

        // Handle external providers like Google, Microsoft, SAML
        if ($twoFAValue === TwoFAType::ENFORCED_FOR_LOCAL->value) {
            // If 2FA is not active, allow flow to continue
            return [
                'success' => true,
                '2FAType' => $twoFAValue
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
                    '2FAType' => $twoFAValue
                ];
            }

            return [
                'success' => false,
                'message' => 'Two-Factor Authentication it\'s required for authentication on the portal. Please visit '
                    . $_SERVER['HTTP_HOST'] . ' to set up 2FA and secure your account.',
                '2FAType' => $twoFAValue,
            ];
        }

        // Fallback for unexpected cases
        return [
            'success' => false,
            'missing_2fa_setting' => true,
            'message' => 'Unhandled Two-Factor Authentication status in the enforcement logic.',
        ];
    }

    private function twoFAUserConfiguration(User $user): array
    {
        // Check if the user's 2FA is active
        if ($this->twoFAService->twoFAisActive($user)) {
            // Handle different user 2FA types
            if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::TOTP->value) {
                return [
                    'type' => UserTwoFactorAuthenticationStatus::TOTP->value,
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
            'message' => 'User does not have Two-Factor Authentication configured.',
            'isActive' => false,
        ];
    }
}
