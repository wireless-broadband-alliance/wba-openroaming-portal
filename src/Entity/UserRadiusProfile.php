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
}
