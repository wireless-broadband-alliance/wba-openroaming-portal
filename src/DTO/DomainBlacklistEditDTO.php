<?php

namespace App\DTO;

use App\Entity\DomainBlacklist;
use App\Enum\DomainMatchType;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

#[CustomAssert\DomainPatternMatch]
class DomainBlacklistEditDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    #[CustomAssert\DomainPattern]
    public ?string $input = null;

    #[Assert\NotBlank]
    public ?DomainMatchType $matchType = null;

    public ?int $id = null;

    public function __construct(?DomainBlacklist $entity = null)
    {
        if ($entity instanceof DomainBlacklist) {
            $this->id = $entity->getId();
            $this->input = $entity->getPattern();
            $this->matchType = $entity->getType();
        }
    }

    public function applyToEntity(DomainBlacklist $entity): void
    {
        $entity
            ->setPattern($this->input)
            ->setType($this->matchType);
    }
}
