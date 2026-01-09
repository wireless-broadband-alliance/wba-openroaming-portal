<?php

namespace App\DTO;

use App\Entity\DomainBlacklist;
use App\Enum\DomainMatchType;
use Symfony\Component\Validator\Constraints as Assert;

class DomainBlacklistLineDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(
        pattern: '/^(\*|\*\.[a-z0-9.-]+\.[a-z]{2,}|[a-z0-9.-]+\.[a-z]{2,})$/i',
        message: 'invalidDomainPattern'
    )]
    public ?string $input = null;

    public ?int $id = null;

    public function __construct(?DomainBlacklist $entity = null)
    {
        if ($entity) {
            $this->id = $entity->getId();

            $this->input = match ($entity->getType()) {
                DomainMatchType::EXACT => $entity->getPattern(),
                DomainMatchType::SUBDOMAIN => '*.' . $entity->getPattern(),
                DomainMatchType::WILDCARD => '*',
            };
        }
    }

    public function applyToEntity(DomainBlacklist $entity): void
    {
        [$pattern, $type] = $this->parseInput($this->input);

        $entity
            ->setPattern($pattern)
            ->setType($type);
    }

    /**
     * @return array{0: string, 1: DomainMatchType}
     */
    private function parseInput(string $input): array
    {
        $input = strtolower(trim($input));

        if ($input === '*') {
            return ['*', DomainMatchType::WILDCARD];
        }

        if (str_starts_with($input, '*.')) {
            return [substr($input, 2), DomainMatchType::SUBDOMAIN];
        }

        return [$input, DomainMatchType::EXACT];
    }
}
