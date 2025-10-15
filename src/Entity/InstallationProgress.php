<?php

namespace App\Entity;

use App\Repository\InstallationProgressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstallationProgressRepository::class)]
class InstallationProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $installationState = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dbOpenRoaming = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dbFreeradius = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $trustedProxies = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $turnstileKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $turnstileSecret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jwtSecretKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jwtPublicKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jwtPassphrase = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailAdmin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordAdmin = null;

    #[ORM\Column]
    #[ORM\JoinColumn(nullable: false)]
    private ?bool $adminConfirmation = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstallationState(): ?string
    {
        return $this->installationState;
    }

    public function setInstallationState(?string $installationState): void
    {
        $this->installationState = $installationState;
    }

    public function getDbOpenRoaming(): ?string
    {
        return $this->dbOpenRoaming;
    }

    public function setDbOpenRoaming(?string $dbOpenRoaming): static
    {
        $this->dbOpenRoaming = $dbOpenRoaming;

        return $this;
    }

    public function getDbFreeradius(): ?string
    {
        return $this->dbFreeradius;
    }

    public function setDbFreeradius(?string $dbFreeradius): static
    {
        $this->dbFreeradius = $dbFreeradius;

        return $this;
    }

    public function getTrustedProxies(): ?string
    {
        return $this->trustedProxies;
    }

    public function setTrustedProxies(?string $trustedProxies): static
    {
        $this->trustedProxies = $trustedProxies;

        return $this;
    }

    public function getTurnstileKey(): ?string
    {
        return $this->turnstileKey;
    }

    public function setTurnstileKey(?string $turnstileKey): static
    {
        $this->turnstileKey = $turnstileKey;

        return $this;
    }

    public function getTurnstileSecret(): ?string
    {
        return $this->turnstileSecret;
    }

    public function setTurnstileSecret(?string $turnstileSecret): static
    {
        $this->turnstileSecret = $turnstileSecret;

        return $this;
    }

    public function getJwtSecretKey(): ?string
    {
        return $this->jwtSecretKey;
    }

    public function setJwtSecretKey(?string $jwtSecretKey): static
    {
        $this->jwtSecretKey = $jwtSecretKey;

        return $this;
    }

    public function getJwtPublicKey(): ?string
    {
        return $this->jwtPublicKey;
    }

    public function setJwtPublicKey(?string $jwtPublicKey): static
    {
        $this->jwtPublicKey = $jwtPublicKey;

        return $this;
    }

    public function getJwtPassphrase(): ?string
    {
        return $this->jwtPassphrase;
    }

    public function setJwtPassphrase(?string $jwtPassphrase): static
    {
        $this->jwtPassphrase = $jwtPassphrase;

        return $this;
    }

    public function getEmailAdmin(): ?string
    {
        return $this->emailAdmin;
    }

    public function setEmailAdmin(?string $emailAdmin): static
    {
        $this->emailAdmin = $emailAdmin;

        return $this;
    }

    public function getPasswordAdmin(): ?string
    {
        return $this->passwordAdmin;
    }

    public function setPasswordAdmin(?string $passwordAdmin): static
    {
        $this->passwordAdmin = $passwordAdmin;

        return $this;
    }

    public function getAdminConfirmation(): ?bool
    {
        return $this->adminConfirmation;
    }

    public function setAdminConfirmation(?bool $adminConfirmation): void
    {
        $this->adminConfirmation = $adminConfirmation;
    }

}
