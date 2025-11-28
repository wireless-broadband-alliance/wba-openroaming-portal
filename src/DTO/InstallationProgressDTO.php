<?php

namespace App\DTO;

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

    public ?string $turnstileKey = null;

    public ?string $turnstileSecret = null;

    public ?string $emailAdmin = null;


    public ?\DateTimeInterface $updatedAt = null;

    public ?\DateTimeInterface $createdAt = null;
}
