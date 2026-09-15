<?php

namespace App\Entity;

use App\Enum\ParamType;
use App\Repository\SMSProviderParamRepository;
use Doctrine\DBAL\Types\Types;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SMSProviderParamRepository::class)]
class SMSProviderParam
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(enumType: ParamType::class)]
    private ?ParamType $type = null;

    #[ORM\Column(length: 255)]
    private ?string $paramType = null;

    #[ORM\Column(type: Types::TEXT, length: 4294967295, nullable: true)]
    private ?string $value = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'smsProviderParams')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SMSProvider $smsProvider = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParamType(): ?string
    {
        return $this->paramType;
    }

    public function setParamType(string $paramType): static
    {
        $this->paramType = $paramType;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getSmsProvider(): ?SMSProvider
    {
        return $this->smsProvider;
    }

    public function setSmsProvider(?SMSProvider $smsProvider): static
    {
        $this->smsProvider = $smsProvider;

        return $this;
    }

    public function getType(): ?ParamType
    {
        return $this->type;
    }

    public function setType(?ParamType $type): void
    {
        $this->type = $type;
    }
}
