<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS)]
class PasswordsMatch extends Constraint
{
    public string $message = 'passwordsDoNotMatch';

    public string $passwordField = 'password';

    public string $confirmPasswordField = 'confirmPassword';

    public function __construct(
        string $passwordField = 'password',
        string $confirmPasswordField = 'confirmPassword',
        ?string $message = null,
        mixed $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->passwordField = $passwordField;
        $this->confirmPasswordField = $confirmPasswordField;

        if ($message !== null) {
            $this->message = $message;
        }
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
