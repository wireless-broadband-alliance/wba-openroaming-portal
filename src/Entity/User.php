<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Security\CustomSamlUserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['uuid'], message: 'There is already an account with this uuid')]
#[ORM\HasLifecycleCallbacks]
class User extends CustomSamlUserFactory implements UserInterface, PasswordAuthenticatedUserInterface

{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $uuid = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'boolean')]
    private $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $saml_identifier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $first_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $last_name = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserRadiusProfile::class)]
    private Collection $userRadiusProfiles;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserExternalAuth::class)]
    private Collection $userExternalAuths;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $verificationCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedAt = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Event::class, orphanRemoval: true)]
    private Collection $event;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(nullable: true)]
    private ?bool $forgot_password_request = null;

    
    public function __construct()
    {
        $this->userRadiusProfiles = new ArrayCollection();
        $this->userExternalAuths = new ArrayCollection();
        $this->event = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->uuid;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->uuid;
    }

    public function setUsername(string $username): self
    {
        $this->uuid = $username;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getSamlIdentifier(): ?string
    {
        return $this->saml_identifier;
    }

    public function setSamlIdentifier(?string $saml_identifier): self
    {
        $this->saml_identifier = $saml_identifier;

        return $this;
    }

    public function setSamlAttributes(array $attributes): void
    {
        $this->uuid = $attributes['samlUuid'][0];
        $this->saml_identifier = $attributes['sAMAccountName'][0];
        $this->email = $attributes['email'][0] ?? '';
        $this->first_name = $attributes['givenName'][0];
        $this->last_name = $attributes['surname'][0] ?? ''; // set surname to empty string if null
        $this->password = 'notused'; //invalid hash so won't ever authenticate
        $this->isVerified = 1;
        // #$this->setLevel(LevelType::NONE);
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(?string $first_name): self
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(?string $last_name): self
    {
        $this->last_name = $last_name;

        return $this;
    }

    /**
     * @return Collection<int, UserRadiusProfile>
     */
    public function getUserRadiusProfiles(): Collection
    {
        return $this->userRadiusProfiles;
    }

    public function addUserRadiusProfile(UserRadiusProfile $userRadiusProfile): self
    {
        if (!$this->userRadiusProfiles->contains($userRadiusProfile)) {
            $this->userRadiusProfiles->add($userRadiusProfile);
            $userRadiusProfile->setUser($this);
        }

        return $this;
    }

    public function removeUserRadiusProfile(UserRadiusProfile $userRadiusProfile): self
    {
        if ($this->userRadiusProfiles->removeElement($userRadiusProfile)) {
            // set the owning side to null (unless already changed)
            if ($userRadiusProfile->getUser() === $this) {
                $userRadiusProfile->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserExternalAuth>
     */
    public function getUserExternalAuths(): Collection
    {
        return $this->userExternalAuths;
    }

    public function addUserExternalAuth(UserExternalAuth $userExternalAuth): self
    {
        if (!$this->userExternalAuths->contains($userExternalAuth)) {
            $this->userExternalAuths->add($userExternalAuth);
            $userExternalAuth->setUser($this);
        }

        return $this;
    }

    public function removeUserExternalAuth(UserExternalAuth $userExternalAuth): self
    {
        if ($this->userExternalAuths->removeElement($userExternalAuth)) {
            // set the owning side to null (unless already changed)
            if ($userExternalAuth->getUser() === $this) {
                $userExternalAuth->setUser(null);
            }
        }

        return $this;
    }

    public function getVerificationCode(): ?string
    {
        return $this->verificationCode;
    }

    public function setVerificationCode(?string $verificationCode): self
    {
        $this->verificationCode = $verificationCode;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getBannedAt(): ?\DateTimeInterface
    {
        return $this->bannedAt;
    }

    public function setBannedAt(?\DateTimeInterface $bannedAt): self
    {
        $this->bannedAt = $bannedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function prePresist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvent(): Collection
    {
        return $this->event;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->event->contains($event)) {
            $this->event->add($event);
            $event->setUser($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->event->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getUser() === $this) {
                $event->setUser(null);
            }
        }

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

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function isForgotPasswordRequest(): ?bool
    {
        return $this->forgot_password_request;
    }

    public function setForgotPasswordRequest(?bool $forgot_password_request): static
    {
        $this->forgot_password_request = $forgot_password_request;

        return $this;
    }

}
