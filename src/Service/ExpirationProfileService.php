<?php

namespace App\Service;

use App\Entity\UserRadiusProfile;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use Exception;

readonly class ExpirationProfileService
{
    public function __construct(
        private SettingRepository $settingRepository,
        private CertificateService $certificateService,
    ) {
    }

    /**
     * Calculate the expiration and notification times for a user profile.
     */
    /**
     * @return array Contains 'limitTime' and 'notifyTime' as DateTime instances.
     * @throws Exception
     */
    public function calculateExpiration(
        string $provider,
        ?string $providerId,
        UserRadiusProfile $userRadiusProfile,
        string $certificatePath,
    ): array {
        $certificateLimitDate = strtotime(
            (string)$this->certificateService->getCertificateExpirationDate($certificatePath)
        );
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (8640)) - 1;
        $defaultExpireDays = ((int)$timeLeft);
        $expireDays = $defaultExpireDays;

        // Determine expiration time based on provider and provider ID
        switch ($provider) {
            case UserProvider::GOOGLE_ACCOUNT->value:
                $expireDays = $this->getSettingValue(SettingName::PROFILE_LIMIT_DATE_GOOGLE->value, $defaultExpireDays);
                break;

            case UserProvider::MICROSOFT_ACCOUNT->value:
                $expireDays = $this->getSettingValue(SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value, $defaultExpireDays);
                break;

            case UserProvider::SAML->value:
                $expireDays = $this->getSettingValue(SettingName::PROFILE_LIMIT_DATE_SAML->value, $defaultExpireDays);
                break;

            case UserProvider::PORTAL_ACCOUNT->value:
                if ($providerId === UserProvider::EMAIL->value) {
                    $expireDays = $this->getSettingValue(SettingName::PROFILE_LIMIT_DATE_EMAIL->value, $defaultExpireDays);
                } elseif ($providerId === UserProvider::PHONE_NUMBER->value) {
                    $expireDays = $this->getSettingValue(SettingName::PROFILE_LIMIT_DATE_SMS->value, $defaultExpireDays);
                }
                break;
        }

        // Calculate time thresholds
        $notifyDays = round($expireDays * 0.9);
        /** @phpstan-ignore-next-line */
        $limitTime = (clone $userRadiusProfile->getIssuedAt())->modify("+{$expireDays} days");
        /** @phpstan-ignore-next-line */
        $notifyTime = (clone $userRadiusProfile->getIssuedAt())->modify("+{$notifyDays} days");

        return [
            'limitTime' => $limitTime,
            'notifyTime' => $notifyTime,
        ];
    }

    /**
     * Get a setting value by name or return a default value.
     */
    private function getSettingValue(string $settingName, int $defaultValue): int
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        return $setting ? (int)$setting->getValue() : $defaultValue;
    }
}
