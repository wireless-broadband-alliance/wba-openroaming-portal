<?php

namespace App\Entity;

use App\Repository\SamlProviderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SamlProviderRepository::class)]
class SamlProvider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $idpEntityId = null;

    #[ORM\Column(length: 255)]
    private ?string $idpSsoUrl = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $idpX509Cert = null;

    #[ORM\Column(length: 255)]
    private ?string $spEntityId = null;

    #[ORM\Column(length: 255)]
    private ?string $spAcsUrl = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    /**
     * @var Collection<int, UserExternalAuth>
     */
    #[ORM\OneToMany(mappedBy: 'samlProvider', targetEntity: UserExternalAuth::class)]
    private Collection $userExternalAuths;

    public function __construct()
    {
        $this->userExternalAuths = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getIdpEntityId(): ?string
    {
        return $this->idpEntityId;
    }

    public function setIdpEntityId(string $idpEntityId): static
    {
        $this->idpEntityId = $idpEntityId;

        return $this;
    }

    public function getIdpSsoUrl(): ?string
    {
        return $this->idpSsoUrl;
    }

    public function setIdpSsoUrl(string $idpSsoUrl): static
    {
        $this->idpSsoUrl = $idpSsoUrl;

        return $this;
    }

    public function getIdpX509Cert(): ?string
    {
        return $this->idpX509Cert;
    }

    public function setIdpX509Cert(string $idpX509Cert): static
    {
        $this->idpX509Cert = $idpX509Cert;

        return $this;
    }

    public function getSpEntityId(): ?string
    {
        return $this->spEntityId;
    }

    public function setSpEntityId(string $spEntityId): static
    {
        $this->spEntityId = $spEntityId;

        return $this;
    }

    public function getSpAcsUrl(): ?string
    {
        return $this->spAcsUrl;
    }

    public function setSpAcsUrl(string $spAcsUrl): static
    {
        $this->spAcsUrl = $spAcsUrl;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, UserExternalAuth>
     */
    public function getUserExternalAuths(): Collection
    {
        return $this->userExternalAuths;
    }

    public function addUserExternalAuth(UserExternalAuth $userExternalAuth): static
    {
        if (!$this->userExternalAuths->contains($userExternalAuth)) {
            $this->userExternalAuths->add($userExternalAuth);
            $userExternalAuth->setSamlProvider($this);
        }

        return $this;
    }

    public function removeUserExternalAuth(UserExternalAuth $userExternalAuth): static
    {
        if ($this->userExternalAuths->removeElement($userExternalAuth)) {
            // set the owning side to null (unless already changed)
            if ($userExternalAuth->getSamlProvider() === $this) {
                $userExternalAuth->setSamlProvider(null);
            }
        }

        return $this;
    }
}
