<?php

namespace App\Entity;

use App\Repository\UserRadiusProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRadiusProfileRepository::class)]
class UserRadiusProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userRadiusProfiles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $radius_token = null;

    #[ORM\Column(length: 255)]
    private ?string $radius_user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $issued_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $valid_until = null;

    #[ORM\Column]
    private ?int $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $revoked_reason = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getRadiusToken(): ?string
    {
        return $this->radius_token;
    }

    public function setRadiusToken(string $radius_token): self
    {
        $this->radius_token = $radius_token;

        return $this;
    }

    public function getRadiusUser(): ?string
    {
        return $this->radius_user;
    }

    public function setRadiusUser(string $radius_user): self
    {
        $this->radius_user = $radius_user;

        return $this;
    }

    public function getIssuedAt(): ?\DateTimeInterface
    {
        return $this->issued_at;
    }

    public function setIssuedAt(?\DateTimeInterface $issued_at): self
    {
        $this->issued_at = $issued_at;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->valid_until;
    }

    public function setValidUntil(?\DateTimeInterface $valid_until): self
    {
        $this->valid_until = $valid_until;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getRevokedReason(): ?string
    {
        return $this->revoked_reason;
    }

    public function setRevokedReason(?string $revoked_reason): static
    {
        $this->revoked_reason = $revoked_reason;

        return $this;
    }
}
