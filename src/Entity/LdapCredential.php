<?php

namespace App\Entity;

use App\Repository\LdapCredentialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LdapCredentialRepository::class)]
class LdapCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $server = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bindUserDn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bindUserPassword = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $searchBaseDn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $searchFilter = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\OneToOne(inversedBy: 'ldapCredential', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?SamlProvider $samlProvider = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServer(): ?string
    {
        return $this->server;
    }

    public function setServer(string $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getBindUserDn(): ?string
    {
        return $this->bindUserDn;
    }

    public function setBindUserDn(?string $bindUserDn): static
    {
        $this->bindUserDn = $bindUserDn;

        return $this;
    }

    public function getBindUserPassword(): ?string
    {
        return $this->bindUserPassword;
    }

    public function setBindUserPassword(?string $bindUserPassword): static
    {
        $this->bindUserPassword = $bindUserPassword;

        return $this;
    }

    public function getSearchBaseDn(): ?string
    {
        return $this->searchBaseDn;
    }

    public function setSearchBaseDn(?string $searchBaseDn): static
    {
        $this->searchBaseDn = $searchBaseDn;

        return $this;
    }

    public function getSearchFilter(): ?string
    {
        return $this->searchFilter;
    }

    public function setSearchFilter(?string $searchFilter): static
    {
        $this->searchFilter = $searchFilter;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getSamlProvider(): ?SamlProvider
    {
        return $this->samlProvider;
    }

    public function setSamlProvider(SamlProvider $samlProvider): static
    {
        $this->samlProvider = $samlProvider;

        return $this;
    }
}
