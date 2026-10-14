<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\CaptchaProviderEnum;
use Symfony\Component\Validator\Constraints as Assert;

class CaptchaDTO
{
    #[Assert\NotNull]
    public ?CaptchaProviderEnum $provider = CaptchaProviderEnum::TURNSTILE;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isTurnstile()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
        ],
    )]
    public ?string $turnstileKey = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isTurnstile()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
        ],
    )]
    public ?string $turnstileSecret = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isRecaptcha3()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
        ],
    )]
    public ?string $recaptcha3Key = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isRecaptcha3()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
        ],
    )]
    public ?string $recaptcha3Secret = null;
}
