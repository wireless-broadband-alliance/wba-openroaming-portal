<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class SAMLProviderUrlValidator extends ConstraintValidator
{
    /**
     * @throws \JsonException
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof SAMLProviderUrl) {
            throw new UnexpectedTypeException($constraint, SAMLProviderUrl::class);
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Example: Validation logic for a valid JSON Url for SAML configuration
        $jsonData = @file_get_contents($value);

        if ($jsonData === false) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ url }}', $value)
                ->setParameter('{{ reason }}', 'The URL could not be reached or is not accessible.')
                ->addViolation();
            return;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ url }}', $value)
                ->setParameter('{{ reason }}', 'The URL does not return a valid JSON response.')
                ->addViolation();
            return;
        }
    }
}
