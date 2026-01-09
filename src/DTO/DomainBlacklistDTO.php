<?php

namespace App\DTO;

use App\Entity\DomainBlacklist;
use Symfony\Component\Validator\Constraints as Assert;

class DomainBlacklistDTO
{
    /** @var DomainBlacklistLineDTO[] */
    #[Assert\Valid]
    public array $lines = [];

    /**
     * @param DomainBlacklist[] $domainBlacklists
     */
    public function __construct(array $domainBlacklists = [])
    {
        foreach ($domainBlacklists as $entity) {
            $this->lines[$entity->getId()] = new DomainBlacklistLineDTO($entity);
        }
    }

    /**
     * @param DomainBlacklist[] $domainBlacklistDB
     * @return DomainBlacklist[]
     */
    public function toEntities(array $domainBlacklistDB): array
    {
        $result = [];

        foreach ($this->lines as $line) {
            if ($line->id !== null) {
                $entity = array_find(
                    $domainBlacklistDB,
                    fn (DomainBlacklist $d) => $d->getId() === $line->id
                ) ?? new DomainBlacklist();
            } else {
                $entity = new DomainBlacklist();
            }

            $line->applyToEntity($entity);
            $result[] = $entity;
        }

        return $result;
    }
}
