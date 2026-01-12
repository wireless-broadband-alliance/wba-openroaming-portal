<?php

namespace App\Entity;

use App\Enum\DomainMatchType;
use App\Enum\DomainOrigin;
use App\Repository\DomainBlacklistRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainBlacklistRepository::class)]
class DomainBlacklist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $pattern;

    #[ORM\Column(length: 32, enumType: DomainMatchType::class)]
    private DomainMatchType $type; // exact | subdomain | wildcard

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(length: 32, enumType: DomainOrigin::class)]
    private ?string $origin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function setPattern(string $pattern): static
    {
        $this->pattern = strtolower($pattern);

        return $this;
    }

    public function getType(): DomainMatchType
    {
        return $this->type;
    }

    public function setType(DomainMatchType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }
}
