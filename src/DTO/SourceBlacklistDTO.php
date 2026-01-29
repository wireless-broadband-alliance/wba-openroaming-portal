<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class SourceBlacklistDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 4096, maxMessage: 'maxCharacters')]
    #[Assert\Url(
        message: 'invalidSource'
    )]
    #[CustomAssert\DomainListUrl]
    public ?string $input = null;

    public ?int $id = null;
}
