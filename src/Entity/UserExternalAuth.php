<?php

namespace App\Entity;

use App\Repository\UserExternalAuthRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserExternalAuthRepository::class)]
class UserExternalAuth
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userExternalAuths')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $provider = null;

    #[ORM\Column(length: 255)]
    private ?string $provider_id = null;

    #[ORM\ManyToOne(inversedBy: 'userExternalAuths')]
    #[ORM\JoinColumn(nullable: true)]
    private ?SamlProvider $samlProvider = null;

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

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderId(): ?string
    {
        return $this->provider_id;
    }

    public function setProviderId(string $provider_id): self
    {
        $this->provider_id = $provider_id;

        return $this;
    }

    public function getSamlProvider(): ?SamlProvider
    {
        return $this->samlProvider;
    }

    public function setSamlProvider(?SamlProvider $samlProvider): static
    {
        $this->samlProvider = $samlProvider;

        return $this;
    }
}
