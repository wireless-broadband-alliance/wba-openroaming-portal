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
    public array $allowedIps = [];

    /**
     * @param array<string, mixed> $settingsData
     */
    public function __construct(array $settingsData = [])
    {
        if (!empty($settingsData['allowed_ips']['value'])) {
            $value = $settingsData['allowed_ips']['value'];
            $this->allowedIps = is_array($value) ? $value : array_values(array_filter(explode(',', (string)$value)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed_ips' => $this->allowedIps,
        ];
    }
}
