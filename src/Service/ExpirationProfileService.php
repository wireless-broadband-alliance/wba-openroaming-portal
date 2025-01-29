<?php

namespace App\Service;

use App\Entity\UserRadiusProfile;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use Exception;

class ExpirationProfileService
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly CertificateService $certificateService
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
        $timeLeft = round(($certificateLimitDate - $realTime) / (60 * 60 * 24)) - 1;
        $defaultExpireDays = ((int)$timeLeft);
        $expireDays = $defaultExpireDays;

        // Determine expiration time based on provider and provider ID
        switch ($provider) {
            case UserProvider::GOOGLE_ACCOUNT:
                $expireDays = $this->getSettingValue('PROFILE_LIMIT_DATE_GOOGLE', $defaultExpireDays);
                break;

            case UserProvider::MICROSOFT_ACCOUNT:
                $expireDays = $this->getSettingValue('PROFILE_LIMIT_DATE_MICROSOFT', $defaultExpireDays);
                break;

            case UserProvider::SAML:
                $expireDays = $this->getSettingValue('PROFILE_LIMIT_DATE_SAML', $defaultExpireDays);
                break;

            case UserProvider::PORTAL_ACCOUNT:
                if ($providerId === UserProvider::EMAIL) {
                    $expireDays = $this->getSettingValue('PROFILE_LIMIT_DATE_EMAIL', $defaultExpireDays);
                } elseif ($providerId === UserProvider::PHONE_NUMBER) {
                    $expireDays = $this->getSettingValue('PROFILE_LIMIT_DATE_SMS', $defaultExpireDays);
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
