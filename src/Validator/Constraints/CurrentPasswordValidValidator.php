<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CurrentPasswordValidValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CurrentPasswordValid) {
            throw new UnexpectedTypeException($constraint, CurrentPasswordValid::class);
        }

        // Let NotBlank handle the empty case; don't double-report.
        if ($value === null || $value === '') {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            return;
        }

        if (!$this->passwordHasher->isPasswordValid($user, (string)$value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
