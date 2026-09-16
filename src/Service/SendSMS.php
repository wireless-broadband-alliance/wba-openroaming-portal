<?php

namespace App\Service;

use App\Entity\SMSProvider;
use App\Entity\User;
use App\Enum\SettingName;
use App\Enum\SMSResponse;
use App\Repository\SettingRepository;
use App\Repository\SMSProviderRepository;
use App\Repository\UserRepository;
use DateTime;
use Random\RandomException;
use RuntimeException;

readonly class SendSMS
{
    public function __construct(
        private SettingRepository $settingRepository,
        private SMSProviderRepository $smsProviderRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws RandomException
     */
    public function sendSmsNoValidation(User $user, string $message): string
    {
        $provider = $this->getActiveProvider();

        $messageLength = $this->verifyMessageLength($message);
        if ($messageLength) {
            $user->setTwoFACode((string)random_int(100000, 999999));
            $user->setTwoFACodeGeneratedAt(new DateTime());
            $user->setTwoFAcodeIsActive(true);
            $this->userRepository->save($user, true);

            $message = 'Verification code is: ' . $user->getTwoFACode();
        }

        $serviceClass = $provider->getSMSProviderType()->getServiceClass();
        $serviceClass::sendSMS($provider, $message, $user);

        if ($messageLength) {
            return SMSResponse::SMS_SUCCESS_CODE->value;
        }
        return SMSResponse::SMS_SUCCESS_LINK->value;
    }

    public function verifyMessageLength(string $message): bool
    {
        return strlen($message) > 612;
    }

    /**
     * Resolves which SMSProvider is active by reading its name off the
     * SMS_ACTIVE_PROVIDER setting, then loading the matching SMSProvider entity.
     */
    private function getActiveProvider(): SMSProvider
    {
        $activeProviderName = $this->settingRepository
            ->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value])
            ?->getValue();

        if ($activeProviderName === null || $activeProviderName === '') {
            throw new RuntimeException(
                'No active SMS provider configured — set a value for the SMS_ACTIVE_PROVIDER setting.'
            );
        }

        $provider = $this->smsProviderRepository->findOneBy(['name' => $activeProviderName]);

        if ($provider === null) {
            throw new RuntimeException(
                sprintf(
                    'Active SMS provider "%s" (from SMS_ACTIVE_PROVIDER) has no matching SMSProvider entity.',
                    $activeProviderName
                )
            );
        }

        return $provider;
    }
}
