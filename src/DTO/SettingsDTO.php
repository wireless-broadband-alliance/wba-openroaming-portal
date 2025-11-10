<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SettingsDTO
{
    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $trustedProxies = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $turnstileKey = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $turnstileSecret = null;

    public ?bool $jwtPassphraseEnable = false;

    public ?string $jwtPassphrase = null;


    #[Assert\Callback]
    public function validateTrustedProxies(ExecutionContextInterface $context)
    {
        $ips = array_map('trim', explode(',', $this->trustedProxies));
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $context->buildViolation('notValidIp')
                    ->atPath('trustedProxies')
                    ->setParameter('%value%', $ip)
                    ->addViolation();
            }
        }
    }
}
