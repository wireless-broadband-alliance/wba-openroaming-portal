<?php

namespace App\Service;

use App\Enum\SettingName;
use App\Repository\SettingRepository;
use InvalidArgumentException;
use OTPHP\TOTP;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TOTPService
{
    private ?string $lastError = null;

    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly CacheItemPoolInterface $cache,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * TOTP -> Time-Based One-Time Password
     * Service that communicates with 2fa applications
     */
    public function generateSecret(): string
    {
        return TOTP::create()->getSecret();
    }

    public function generateTOTP(string $secret): string
    {
        if ($secret === '' || $secret === '0') {
            throw new InvalidArgumentException('TOTP secret cannot be empty.');
        }

        $labelSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::TWO_FACTOR_AUTH_APP_LABEL->value
        ]);
        $issuerSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value
        ]);

        $label = $labelSetting?->getValue();
        $issuer = $issuerSetting?->getValue();

        if (empty($label)) {
            throw new InvalidArgumentException('TOTP label cannot be empty.');
        }

        if (empty($issuer)) {
            throw new InvalidArgumentException('TOTP issuer cannot be empty.');
        }

        $totp = TOTP::create($secret);
        $totp->setLabel($label);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri(); // URI for QR Code
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function verifyTOTP(string $secret, string $code): bool
    {
        $this->lastError = null;

        if ($secret === '' || $secret === '0') {
            throw new InvalidArgumentException('TOTP secret cannot be empty.');
        }

        if ($code === '' || $code === '0') {
            throw new InvalidArgumentException('TOTP code cannot be empty.');
        }

        $totp = TOTP::create($secret);

        if (!$totp->verify($code)) {
            $this->lastError =  $this->translator->trans('invalidCodeTOTP', [], 'controllers');
            return false;
        }

        $item = $this->cache->getItem('totp_code');

        if ($item->isHit()) {
            $data = $item->get();

            if (hash_equals($data['code'], $code)) {
                $remainingTime = max(0, $data['expires_at'] - time());

                $this->lastError =  $this->translator->trans(
                    'replyCodeTotp',
                    [
                        '%remainingTime%' => $remainingTime,
                    ],
                    'controllers'
                );
                return false;
            }
        }

        $expiresAt = time() + 30;

        $item->set([
            'code' => $code,
            'expires_at' => $expiresAt
        ]);
        $item->expiresAfter(30);

        $this->cache->save($item);

        return true;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
