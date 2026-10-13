<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\CaptchaProviderEnum;
use Symfony\Component\Validator\Constraints as Assert;

class CaptchaDTO
{
    #[Assert\NotNull]
    public CaptchaProviderEnum $provider = CaptchaProviderEnum::OFF;

    #[Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    #[Assert\When(
        expression: 'this.provider.isTurnstile()',
        constraints: [new Assert\NotBlank(message: 'notBlank')],
    )]
    public ?string $turnstileKey = null;

    #[Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    #[Assert\When(
        expression: 'this.provider.isTurnstile()',
        constraints: [new Assert\NotBlank(message: 'notBlank')],
    )]
    public ?string $turnstileSecret = null;

    #[Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    #[Assert\When(
        expression: 'this.provider.isRecaptcha3()',
        constraints: [new Assert\NotBlank(message: 'notBlank')],
    )]
    public ?string $recaptcha3Key = null;

    #[Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    #[Assert\When(
        expression: 'this.provider.isRecaptcha3()',
        constraints: [new Assert\NotBlank(message: 'notBlank')],
    )]
    public ?string $recaptcha3Secret = null;
}
