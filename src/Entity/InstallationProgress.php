<?php

namespace App\Entity;

use App\Enum\ProcessStatusType;
use App\Repository\InstallationProgressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstallationProgressRepository::class)]
class InstallationProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(nullable: true, enumType: ProcessStatusType::class)]
    private ?ProcessStatusType $installationState = ProcessStatusType::IN_PROGRESS;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dbOpenRoaming = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dbFreeradius = null;

    /**
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $trustedProxies = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $turnstileKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $turnstileSecret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jwtPassphrase = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailAdmin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordAdmin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $confirmCodeAdmin = null;

    #[ORM\Column]
    #[ORM\JoinColumn(nullable: false)]
    private ?bool $adminConfirmation = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstallationState(): ?ProcessStatusType
    {
        return $this->installationState;
    }

    public function setInstallationState(?ProcessStatusType $installationState): void
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

    /**
     * @return string[]
     */
    public function getTrustedProxies(): array
    {
        return $this->trustedProxies ?? [];
    }

    /**
     * @param string[]|null $trustedProxies
     */
    public function setTrustedProxies(?array $trustedProxies): static
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

    public function getConfirmCodeAdmin(): ?string
    {
        return $this->confirmCodeAdmin;
    }

    public function setConfirmCodeAdmin(?string $confirmCodeAdmin): void
    {
        $this->confirmCodeAdmin = $confirmCodeAdmin;
    }

    public function getAdminConfirmation(): ?bool
    {
        return $this->adminConfirmation;
    }

    public function setAdminConfirmation(?bool $adminConfirmation): void
    {
        $this->adminConfirmation = $adminConfirmation;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
