<?php

namespace App\DTO;

use App\Entity\DomainBlacklist;
use App\Enum\DomainMatchType;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class DomainBlacklistDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[CustomAssert\DomainPattern]
    public ?string $input = null;

    public ?int $id = null;

    public function __construct(?DomainBlacklist $entity = null)
    {
        if ($entity instanceof DomainBlacklist) {
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
