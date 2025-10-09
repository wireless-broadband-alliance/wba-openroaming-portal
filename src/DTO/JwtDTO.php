<?php

namespace App\DTO;

use App\Enum\OperationMode;
use Symfony\Component\Validator\Constraints as Assert;

class JwtDTO
{

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $jwtSecretKey = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $jwtPublicKey = null;

    public ?bool $jwtPassphraseEnable = false;

    public ?string $jwtPassphrase = null;

}