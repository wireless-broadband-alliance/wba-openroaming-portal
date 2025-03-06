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

    #[ORM\Column(length: 255, nullable: false)]
    private string $idpEntityId;

    #[ORM\Column(length: 255, nullable: false)]
    private string $idpSsoUrl;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $idpX509Cert;

    #[ORM\Column(length: 255, nullable: false)]
    private string $spEntityId;

    #[ORM\Column(length: 255, nullable: false)]
    private string $spAcsUrl;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isActive;

    /**
     * @var Collection<int, UserExternalAuth>
     */
    #[ORM\OneToMany(mappedBy: 'samlProvider', targetEntity: UserExternalAuth::class)]
    private Collection $userExternalAuths;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $isLDAPActive = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ldapServer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ldapBindUserDn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ldapBindUserPassword = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ldapSearchBaseDn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ldapSearchFilter = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $ldapUpdatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $btnLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $btnDescription = null;

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

    public function setIdpEntityId(?string $idpEntityId): static
    {
        $this->idpEntityId = $idpEntityId;

        return $this;
    }

    public function getIdpSsoUrl(): ?string
    {
        return $this->idpSsoUrl;
    }

    public function setIdpSsoUrl(?string $idpSsoUrl): static
    {
        $this->idpSsoUrl = $idpSsoUrl;

        return $this;
    }

    public function getIdpX509Cert(): ?string
    {
        return $this->idpX509Cert;
    }

    public function setIdpX509Cert(?string $idpX509Cert): static
    {
        $this->idpX509Cert = $idpX509Cert;

        return $this;
    }

    public function getSpEntityId(): ?string
    {
        return $this->spEntityId;
    }

    public function setSpEntityId(?string $spEntityId): static
    {
        $this->spEntityId = $spEntityId;

        return $this;
    }

    public function getSpAcsUrl(): ?string
    {
        return $this->spAcsUrl;
    }

    public function setSpAcsUrl(?string $spAcsUrl): static
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
        // Set the owning side to null (unless already changed)
        if (
            $this->userExternalAuths->removeElement($userExternalAuth) && $userExternalAuth->getSamlProvider() === $this
        ) {
            $userExternalAuth->setSamlProvider(null);
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getIsLDAPActive(): ?bool
    {
        return $this->isLDAPActive;
    }

    public function setIsLDAPActive(bool $isLDAPActive): static
    {
        $this->isLDAPActive = $isLDAPActive;

        return $this;
    }

    public function getLdapServer(): ?string
    {
        return $this->ldapServer;
    }

    public function setLdapServer(?string $ldapServer): static
    {
        $this->ldapServer = $ldapServer;

        return $this;
    }

    public function getLdapBindUserDn(): ?string
    {
        return $this->ldapBindUserDn;
    }

    public function setLdapBindUserDn(?string $ldapBindUserDn): static
    {
        $this->ldapBindUserDn = $ldapBindUserDn;

        return $this;
    }

    public function getLdapBindUserPassword(): ?string
    {
        return $this->ldapBindUserPassword;
    }

    public function setLdapBindUserPassword(?string $ldapBindUserPassword): static
    {
        $this->ldapBindUserPassword = $ldapBindUserPassword;

        return $this;
    }

    public function getLdapSearchBaseDn(): ?string
    {
        return $this->ldapSearchBaseDn;
    }

    public function setLdapSearchBaseDn(?string $ldapSearchBaseDn): static
    {
        $this->ldapSearchBaseDn = $ldapSearchBaseDn;

        return $this;
    }

    public function getLdapSearchFilter(): ?string
    {
        return $this->ldapSearchFilter;
    }

    public function setLdapSearchFilter(?string $ldapSearchFilter): static
    {
        $this->ldapSearchFilter = $ldapSearchFilter;

        return $this;
    }

    public function getLdapUpdatedAt(): ?\DateTimeInterface
    {
        return $this->ldapUpdatedAt;
    }

    public function setLdapUpdatedAt(?\DateTimeInterface $ldapUpdatedAt): static
    {
        $this->ldapUpdatedAt = $ldapUpdatedAt;

        return $this;
    }

    public function getBtnLabel(): ?string
    {
        return $this->btnLabel;
    }

    public function setBtnLabel(?string $btnLabel): static
    {
        $this->btnLabel = $btnLabel;

        return $this;
    }

    public function getBtnDescription(): ?string
    {
        return $this->btnDescription;
    }

    public function setBtnDescription(?string $btnDescription): static
    {
        $this->btnDescription = $btnDescription;

        return $this;
    }
}
