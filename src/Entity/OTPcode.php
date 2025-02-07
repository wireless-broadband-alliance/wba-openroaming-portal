<?php

namespace App\Entity;

use App\Repository\OTPcodeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OTPcodeRepository::class)]
class OTPcode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'oTPcodes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TwoFactorAuthentication $twoFactorAuthentication = null;

    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTwoFactorAuthentication(): ?TwoFactorAuthentication
    {
        return $this->twoFactorAuthentication;
    }

    public function setTwoFactorAuthentication(?TwoFactorAuthentication $twoFactorAuthentication): static
    {
        $this->twoFactorAuthentication = $twoFactorAuthentication;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
