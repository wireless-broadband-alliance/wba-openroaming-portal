<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\CaptchaProviderEnum;
use App\Enum\ProcessStatusType;

class InstallationProgressDTO
{
    public ?ProcessStatusType $installationState = null;

    public ?string $dbOpenRoamingUserName = null;

    public ?string $dbOpenRoamingPassword = null;

    public ?string $dbOpenRoamingIp = null;

    public ?string $dbOpenRoamingPort = null;

    public ?string $dbFreeradiusUserName = null;

    public ?string $dbFreeradiusPassword = null;

    public ?string $dbFreeradiusIp = null;

    public ?string $dbFreeradiusPort = null;

    public ?string $trustedProxies = null;

    public ?string $captchaProvider = CaptchaProviderEnum::OFF->value;

    public ?string $turnstileKey = null;

    public ?string $turnstileSecret = null;

    public ?string $recaptcha3Key = null;

    public ?string $recaptcha3Secret = null;

    public ?string $emailAdmin = null;

    public ?string $securityContact = null;

    public ?\DateTimeInterface $securityExpires = null;

    public ?string $securityPgpFingerprint = null;

    public ?\DateTimeInterface $updatedAt = null;

    public ?\DateTimeInterface $createdAt = null;
}
