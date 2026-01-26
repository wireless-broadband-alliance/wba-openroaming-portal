<?php

namespace App\Entity;

use App\Enum\DomainMatchType;
use App\Enum\DomainOrigin;
use App\Repository\DomainBlacklistRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainBlacklistRepository::class)]
#[ORM\Table(
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_domain_pattern',
            columns: ['pattern']
        )
    ]
)
]
class DomainBlacklist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $pattern;

    #[ORM\Column(length: 1, enumType: DomainMatchType::class)]
    private DomainMatchType $type; // exact = 0 | subdomain = 1 | wildcard = 2

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 32, enumType: DomainOrigin::class)]
    private ?DomainOrigin $origin = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getOrigin(): ?DomainOrigin
    {
        return $this->origin;
    }

    public function setOrigin(DomainOrigin $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }
}
