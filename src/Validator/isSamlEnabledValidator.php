<?php

namespace App\Validator;

use App\Repository\SamlProviderRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class isSamlEnabledValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SamlProviderRepository $repository
    ) {
    }

    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        // Ensure the constraint is of the correct type
        if (!$constraint instanceof isSamlEnabled) {
            throw new \InvalidArgumentException(sprintf(
                'Expected instance of %s, got %s',
                isSamlEnabled::class,
                get_class($constraint)
            ));
        }

        if ($value !== "true") {
            return;
        }

        // Fetch an active SAML provider
        $activeProvider = $this->repository->findOneBy(['isActive' => true]);

        // If no active provider exists, reject the field value with a validation message
        if (!$activeProvider) {
            $this->context->buildViolation($constraint->message)
            ->addViolation();
        }
    }
}
