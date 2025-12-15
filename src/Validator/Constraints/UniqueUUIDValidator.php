<?php

namespace App\Validator\Constraints;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueUUIDValidator extends ConstraintValidator
{
  public function __construct(
      private readonly UserRepository $userRepository
  ) {
  }

  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof UniqueUUID) {
      return; // safety check
    }

    // skip empty values
    if ($value === null || $value === '') {
      return;
    }

    $existingUser = $this->userRepository->findOneBy(['uuid' => $value]);

    if ($existingUser) {
      $this->context->buildViolation($constraint->message)
          ->setParameter('{{ uuid }}', $value)
          ->addViolation();
    }
  }
}
