<?php

namespace App\Entity;

use App\Repository\UserRadiusProfileRepository;
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

    #[ORM\Column]
    private ?\DateTimeImmutable $issued_at = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $valid_until = null;

    #[ORM\Column]
    private ?int $status = null;

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

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issued_at;
    }

    public function setIssuedAt(\DateTimeImmutable $issued_at): self
    {
        $this->issued_at = $issued_at;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->valid_until;
    }

    public function setValidUntil(?\DateTimeImmutable $valid_until): self
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
}
