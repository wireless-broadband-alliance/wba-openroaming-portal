<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class SourceBlacklistDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 4096, maxMessage: 'maxCharacters')]
    #[Assert\Url(
        message: 'invalidSource'
    )]
    public ?string $input = null;

    public ?int $id = null;
}
