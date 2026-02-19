<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class CloudflareDTO
{
    #[NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'maxCharacters'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9.-]+$/',
        message: 'invalidFormat'
    )]
    public ?string $host = null;

    #[NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(
        min: 20,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $token = null;
}
