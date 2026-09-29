<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validator\Constraints as CustomAssert;
use Symfony\Component\Validator\Constraints as Assert;

class IpRestrictionSettingsDTO
{
    /**
     * @var list<string>
     */
    #[Assert\All([
        new CustomAssert\IpOrCidr()
    ])]
    public array $trustedProxies = [];

    /**
     * @var list<string>
     */
    #[Assert\All([
        new CustomAssert\IpOrCidr()
    ])]
    public array $allowedIps = [];

    public bool $enableIpRestriction = false;

    /**
     * @param array<string, mixed> $settingsData
     */
    public function __construct(array $settingsData = [])
    {
        if (!empty($settingsData['trusted_proxies']['value'])) {
            $value = $settingsData['trusted_proxies']['value'];
            $this->trustedProxies = is_array($value) ? $value : array_filter(explode(',', (string)$value));
        }

        if (!empty($settingsData['allowed_ips']['value'])) {
            $value = $settingsData['allowed_ips']['value'];
            $this->allowedIps = is_array($value) ? $value : array_filter(explode(',', (string)$value));
        }

        if (isset($settingsData['enable_ip_restriction']['value'])) {
            $this->enableIpRestriction = (bool)$settingsData['enable_ip_restriction']['value'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trusted_proxies' => $this->trustedProxies,
            'allowed_ips' => $this->allowedIps,
            'enable_ip_restriction' => $this->enableIpRestriction,
        ];
    }
}
