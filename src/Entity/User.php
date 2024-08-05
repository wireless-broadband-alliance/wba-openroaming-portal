<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Api\V1\Controller\GenerateJwtSamlController;
use App\Api\V1\Controller\GetCurrentUserController;
use App\Api\V1\Controller\AuthsController;
use App\Api\V1\Controller\LocalRegistrationController;
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
#[ApiResource(
    description: "The User entity returns values related to a user.",
    operations: [
        new GetCollection(
            uriTemplate: '/v1/user',
            controller: GetCurrentUserController::class,
            shortName: 'User',
            security: "is_granted('ROLE_USER')",
            securityMessage: "You don't have permission to access this resource",
            name: 'api_get_current_user',
            openapiContext: [
                'summary' => 'Retrieve current authenticated user',
                'description' => 'This endpoint returns the details of the currently authenticated user.',
                'responses' => [
                    '200' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'uuid' => ['type' => 'string'],
                                        'email' => ['type' => 'string'],
                                        'roles' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string']
                                        ],
                                        'isVerified' => ['type' => 'boolean'],
                                        'phone_number' => ['type' => 'string'],
                                        'firstName' => ['type' => 'string'],
                                        'lastName' => ['type' => 'string'],
                                        'verification_code' => ['type' => 'int'],
                                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                                        'bannedAt' => ['type' => 'string', 'format' => 'date-time'],
                                        'deletedAt' => ['type' => 'string', 'format' => 'date-time'],
                                        'forgot_password_request' => ['type' => 'boolean'],
                                    ],
                                ],
                                'example' => [
                                    'uuid' => 'abc123',
                                    'email' => 'user@example.com',
                                    'roles' => ["ROLE_USER"],
                                    'isVerified' => true,
                                    'phone_number' => '+19700XXXXXX',
                                    'firstName' => 'John',
                                    'lastName' => 'Doe',
                                    'verification_code' => 123456,
                                    'createdAt' => '2023-01-01T00:00:00+00:00',
                                    'bannedAt' => '2023-01-01T00:00:00+00:00',
                                    'deletedAt' => '2023-01-01T00:00:00+00:00',
                                    'forgot_password_request' => false
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Unauthorized',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'Unauthorized',
                                ],
                            ],
                        ],
                    ],
                ],
                'security' => [
                    [
                        'BearerAuth' => [
                            'scheme' => 'Bearer',
                            'bearerFormat' => 'JWT',
                            'example' => 'Bearer <JWT_TOKEN>',
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/local',
            controller: AuthsController::class,
            shortName: 'User Auth',
            name: 'api_auth_local',
            openapiContext: [
                'summary' => 'Authenticate a user locally',
                'description' => 'This endpoint authenticates a user using their UUID and password.',
                'requestBody' => [
                    'description' => 'User credentials',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uuid' => ['type' => 'string', 'example' => 'user-uuid-example'],
                                    'password' => ['type' => 'string', 'example' => 'user-password-example'],
                                ],
                                'required' => ['uuid', 'password'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Authenticated user details and JWT token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => ['type' => 'string', 'example' => 'jwt-token-example'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'uuid' => ['type' => 'string', 'example' => 'user-uuid-example'],
                                                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => ['type' => 'string', 'example' => 'John'],
                                                'last_name' => ['type' => 'string', 'example' => 'Doe'],
                                                'isVerified' => ['type' => 'boolean', 'example' => true],
                                                'createdAt' => ['type' => 'string', 'format' => 'date-time', 'example' => '2023-01-01T00:00:00+00:00'],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => ['type' => 'string', 'example' => 'PortalAccount'],
                                                            'provider_id' => ['type' => 'string', 'example' => 'provider-id-example'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'token' => 'jwt-token-example',
                                    'user' => [
                                        'uuid' => 'user-uuid-example',
                                        'email' => 'user@example.com',
                                        'roles' => ['ROLE_USER'],
                                        'first_name' => 'John',
                                        'last_name' => 'Doe',
                                        'isVerified' => true,
                                        'createdAt' => '2023-01-01T00:00:00+00:00',
                                        'user_external_auths' => [
                                            [
                                                'provider' => 'PortalAccount',
                                                'provider_id' => 'provider-id-example',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'Invalid data or credentials',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'Invalid data',
                                ],
                            ],
                        ],
                    ],
                    '403' => [
                        'description' => 'Provider not allowed',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'Invalid credentials - Provider not allowed',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/saml',
            controller: AuthsController::class,
            shortName: 'User Auth',
            name: 'api_auth_saml',
            openapiContext: [
                'summary' => 'Authenticate a user via SAML',
                'description' => 'This endpoint authenticates a user using their SAML account name.',
                'requestBody' => [
                    'description' => 'SAML account name',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'sAMAccountName' => ['type' => 'string', 'example' => 'saml-account-name-example'],
                                ],
                                'required' => ['sAMAccountName'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Authenticated user details and JWT token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => ['type' => 'string', 'example' => 'jwt-token-example'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer', 'example' => 1],
                                                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'uuid' => ['type' => 'string', 'example' => 'user-uuid-example'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => ['type' => 'string', 'example' => 'John'],
                                                'last_name' => ['type' => 'string', 'example' => 'Doe'],
                                                'isVerified' => ['type' => 'boolean', 'example' => true],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => ['type' => 'string', 'example' => 'PortalAccount'],
                                                            'provider_id' => ['type' => 'string', 'example' => 'provider-id-example'],
                                                        ],
                                                    ],
                                                ],
                                                'createdAt' => ['type' => 'string', 'format' => 'date-time', 'example' => '2023-01-01 00:00:00'],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'token' => 'jwt-token-example',
                                    'user' => [
                                        'id' => 1,
                                        'email' => 'user@example.com',
                                        'uuid' => 'user-uuid-example',
                                        'roles' => ['ROLE_USER'],
                                        'first_name' => 'John',
                                        'last_name' => 'Doe',
                                        'isVerified' => true,
                                        'user_external_auths' => [
                                            [
                                                'provider' => 'PortalAccount',
                                                'provider_id' => 'provider-id-example',
                                            ],
                                        ],
                                        'createdAt' => '2023-01-01 00:00:00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Invalid data',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'Invalid data',
                                ],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'User not found or provider not associated',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'User not found',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/google',
            controller: AuthsController::class,
            shortName: 'User Auth',
            name: 'api_auth_google'
        ),
        new Post(
            uriTemplate: '/v1/auth/local/register/',
            controller: LocalRegistrationController::class,
            shortName: 'User Auth Register',
            name: 'api_auth_local_register'
        ),
        new Post(
            uriTemplate: '/v1/auth/sms/register/',
            controller: LocalRegistrationController::class,
            shortName: 'User Auth Register',
            name: 'api_auth_sms_register'
        )
    ],
)]
#[UniqueEntity(fields: ['uuid'], message: 'There is already an account with this uuid')]
#[ORM\HasLifecycleCallbacks]
class User extends CustomSamlUserFactory implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    /**
     * User Unique Identification Definition
     */
    #[ORM\Column(length: 180, unique: true)]
    private ?string $uuid = null;
    /**
     *  Associated Roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;
    /**
     * System verification status
     */
    #[ORM\Column(type: 'boolean')]
    private $isVerified = false;
    /**
     * User saml identifier (not mandatory, only if it's a SAML account)
     */
    #[ORM\Column(length: 255, nullable: true)]
    public ?string $saml_identifier = null;
    /**
     * User first name
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $first_name = null;
    /**
     * User last name
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $last_name = null;
    /**
     * User radius account identifier to generate passpoint provisioning profiles (foreign key)
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserRadiusProfile::class)]
    private Collection $userRadiusProfiles;
    /**
     * User radius account identifier for authentications request (foreign key)
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserExternalAuth::class)]
    private Collection $userExternalAuths;
    /**
     * User last verification code
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $verificationCode = null;
    /**
     * User google account identificationr (not mandatoru, only if it's a google account)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;
    /**
     * User creation date
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;
    /**
     * User ban date
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedAt = null;
    /**
     * User event identifcation logger (foreign key)
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Event::class, orphanRemoval: true)]
    private Collection $event;
    /**
     * User deletion date
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;
    /**
     * User phone number (not mandatory, only if it's a phone number account)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneNumber = null;
    /**
     * User forgot_passsowrd_request
     */
    #[ORM\Column(nullable: true)]
    private ?bool $forgot_password_request = null;
    /**
     * User deleted data identification (foreign key)
     */
    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?DeletedUserData $deletedUserData = null;


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
}
