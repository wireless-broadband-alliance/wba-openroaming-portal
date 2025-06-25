<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Api\V1\Controller\UserAccountController;
use App\Repository\DeletedUserDataRepository;
use ArrayObject;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeletedUserDataRepository::class)]
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
