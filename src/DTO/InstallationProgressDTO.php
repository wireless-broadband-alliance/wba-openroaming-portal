<?php

namespace App\DTO;

class InstallationProgressDTO
{

    public ?string $installationState = null;

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