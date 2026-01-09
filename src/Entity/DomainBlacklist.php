<?php

namespace App\Entity;

use App\Enum\DomainMatchType;
use App\Repository\DomainBlacklistRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainBlacklistRepository::class)]
class DomainBlacklist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $pattern;

    #[ORM\Column(length: 32, enumType: DomainMatchType::class)]
    private DomainMatchType $type; // exact | subdomain | wildcard

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
}

