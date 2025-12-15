<?php

namespace App\Validator\Constraints;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
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
      return;
    }

    if (!$value) {
      return;
    }

    // Try to get the email from the parent object (DTO)
    $dto = $this->context->getObject(); // this is the DTO instance
    $currentEmail = $dto->email ?? null;

    $existingUser = $this->userRepository->findOneBy(['uuid' => $value]);

    if ($existingUser && $existingUser->getEmail() !== $currentEmail) {
      $this->context->buildViolation($constraint->message)
          ->setParameter('{{ uuid }}', $value)
          ->addViolation();
    }
  }
}
