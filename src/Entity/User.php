<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Api\V1\Controller\AuthController;
use App\Api\V1\Controller\GetCurrentUserController;
use App\Api\V1\Controller\RegistrationController;
use App\Api\V1\Controller\TwoFAController;
use App\Repository\UserRepository;
use App\Security\CustomSamlUserFactory;
use ArrayObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
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

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

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

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedAt = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Event::class, orphanRemoval: true)]
    private Collection $event;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notification;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(type: 'phone_number', nullable: true)]
    private ?PhoneNumber $phoneNumber = null;

    #[ORM\Column(nullable: true)]
    private ?bool $forgot_password_request = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?DeletedUserData $deletedUserData = null;

    #[ORM\Column]
    private ?bool $isDisabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twoFAsecret = null;

    #[ORM\Column(length: 255)]
    private int $twoFAtype = 0;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $twoFAcode = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $twoFAcodeIsActive = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $twoFAcodeGeneratedAt = null;

    /**
     * @var Collection<int, OTPcode>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: OTPcode::class, orphanRemoval: true)]
    private Collection $oTPcodes;


    public function __construct()
    {
        $this->userRadiusProfiles = new ArrayCollection();
        $this->userExternalAuths = new ArrayCollection();
        $this->event = new ArrayCollection();
    }

    public function getTwoFAcodeIsActive(): ?bool
    {
        return $this->twoFAcodeIsActive;
    }

    public function setTwoFAcodeIsActive(?bool $twoFAcodeIsActive): void
    {
        $this->twoFAcodeIsActive = $twoFAcodeIsActive;
    }


    public function getTwoFAsecret(): ?string
    {
        return $this->twoFAsecret;
    }

    public function setTwoFAsecret(?string $twoFAsecret): void
    {
        $this->twoFAsecret = $twoFAsecret;
    }

    public function getTwoFAtype(): int
    {
        return $this->twoFAtype;
    }

    public function setTwoFAtype(int $twoFAtype): void
    {
        $this->twoFAtype = $twoFAtype;
    }

    public function getTwoFAcode(): ?string
    {
        return $this->twoFAcode;
    }

    public function setTwoFAcode(?string $twoFAcode): void
    {
        $this->twoFAcode = $twoFAcode;
    }

    public function getTwoFAcodeGeneratedAt(): ?\DateTimeInterface
    {
        return $this->twoFAcodeGeneratedAt;
    }

    public function setTwoFAcodeGeneratedAt(?\DateTimeInterface $twoFAcodeGeneratedAt): void
    {
        $this->twoFAcodeGeneratedAt = $twoFAcodeGeneratedAt;
    }

    /**
     * @return Collection<int, OTPcode>
     */
    public function getOTPcodes(): Collection
    {
        return $this->oTPcodes;
    }

    public function addOTPcode(OTPcode $oTPcode): static
    {
        if (!$this->oTPcodes->contains($oTPcode)) {
            $this->oTPcodes->add($oTPcode);
            $oTPcode->setUser($this);
        }

        return $this;
    }

    public function removeOTPcode(OTPcode $oTPcode): static
    {
        // set the owning side to null (unless already changed)
        if (
            $this->oTPcodes->removeElement($oTPcode) &&
            ($this->oTPcodes->removeElement($oTPcode) &&
                ($this->oTPcodes->removeElement($oTPcode) &&
                    ($this->oTPcodes->removeElement($oTPcode))))
        ) {
            $this->oTPcodes->removeElement($oTPcode);
        }
        return $this;
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
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
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

    public function setSamlAttributes(array $attributes): void
    {
        $this->uuid = $attributes['samlUuid'][0];
        $this->email = $attributes['email'][0] ?? '';
        $this->first_name = $attributes['givenName'][0];
        $this->last_name = $attributes['surname'][0] ?? ''; // set surname to empty string if null
        $this->password = 'notused'; //invalid hash so won't ever authenticate
        $this->isVerified = true;
        $this->isDisabled = false;
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
        // Set the owning side to null (unless already changed)
        if ($this->userRadiusProfiles->removeElement($userRadiusProfile) && $userRadiusProfile->getUser() === $this) {
            $userRadiusProfile->setUser(null);
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
        // Set the owning side to null (unless already changed)
        if ($this->userExternalAuths->removeElement($userExternalAuth) && $userExternalAuth->getUser() === $this) {
            $userExternalAuth->setUser(null);
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
        if (!$this->createdAt instanceof \DateTimeInterface) {
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
        // Set the owning side to null (unless already changed)
        if ($this->event->removeElement($event) && $event->getUser() === $this) {
            $event->setUser(null);
        }

        return $this;
    }

    public function getNotification(): Collection
    {
        return $this->notification;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->event->contains($notification)) {
            $this->event->add($notification);
            $notification->setUser($this);
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

    public function getPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?PhoneNumber $phoneNumber): static
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

    public function getDeletedUserData(): ?DeletedUserData
    {
        return $this->deletedUserData;
    }

    public function setDeletedUserData(DeletedUserData $deletedUserData): static
    {
        // set the owning side of the relation if necessary
        if ($deletedUserData->getUser() !== $this) {
            $deletedUserData->setUser($this);
        }

        $this->deletedUserData = $deletedUserData;

        return $this;
    }

    public function toApiResponse(array $additionalData = []): array
    {
        $userExternalAuths = $this->getUserExternalAuths()->map(
            fn(UserExternalAuth $userExternalAuth) => [
                'provider' => $userExternalAuth->getProvider(),
                'provider_id' => $userExternalAuth->getProviderId(),
            ]
        )->toArray();

        $responseData = [
            'uuid' => $this->getUuid(),
            'email' => $this->getEmail(),
            'roles' => $this->getRoles(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'user_external_auths' => $userExternalAuths,
        ];

        return array_merge($responseData, $additionalData);
    }

    public function isDisabled(): ?bool
    {
        return $this->isDisabled;
    }

    public function setDisabled(bool $isDisabled): static
    {
        $this->isDisabled = $isDisabled;

        return $this;
    }
}
