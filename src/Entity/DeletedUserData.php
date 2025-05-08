<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Api\V1\Controller\UserAccountController;
use App\Repository\DeletedUserDataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeletedUserDataRepository::class)]
#[ApiResource(
    operations: [
        new Delete(
            uriTemplate: '/api/v1/userAccount/deletion',
            controller: UserAccountController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'User Account was deleted successfully.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => true,
                                        ],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'message' => [
                                                    'type' => 'string',
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'example' => 'User with UUID "%s" successfully deleted.',
                                                    // phpcs:enable
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'message' => 'User with UUID "test@openroaming.com" successfully deleted.',
                                        // phpcs:enable
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Delete the authenticated user account',
                description: 'This endpoint deletes the currently authenticated user account.
                 Depending on the authentication method, the request body may require a password (Portal Account),
                  a SAMLResponse (SAML), or an authorization code (Google/Microsoft). 
                  The request verifies the provided authentication details before performing the account deletion.',
                parameters: [
                    new Parameter(
                        name: 'Authorization',
                        in: 'header',
                        description: 'Bearer token required for authentication. Use the format: `Bearer <JWT token>`.',
                        required: true,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
                security: [
                    [
                        'bearerAuth' => [],
                    ]
                ],
            ),
            shortName: 'User Account',
            name: 'api_user_account_deletion',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
    ],
)]
class DeletedUserData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $pgpEncryptedJsonFile = null;

    #[ORM\OneToOne(inversedBy: 'deletedUserData', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPgpEncryptedJsonFile(): ?string
    {
        return $this->pgpEncryptedJsonFile;
    }

    public function setPgpEncryptedJsonFile(string $pgpEncryptedJsonFile): static
    {
        $this->pgpEncryptedJsonFile = $pgpEncryptedJsonFile;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
