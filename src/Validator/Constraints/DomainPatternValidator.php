<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DomainPatternValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainPattern) {
            return;
        }

        // skip empty values (let NotBlank handle those)
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        $value = strtolower(trim($value));

        // Global wildcard
        if ($value === '*') {
            return;
        }

        // Regex pattern
        if ($this->isRegex($value)) {
            return;
        }

        // Wildcard subdomain (*.example.com)
        if (str_starts_with($value, '*.')) {
            if ($this->isValidDomain(substr($value, 2))) {
                return;
            }

            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        // Exact domain
        if ($this->isValidDomain($value)) {
            return;
        }

        // Invalid domain
        $this->context->buildViolation($constraint->message)->addViolation();
    }

    private function isRegex(string $value): bool
    {
        if (!preg_match('#^/.+/[imsxuADU]*$#', $value)) {
            return false;
        }

        // ensure regex compiles
        set_error_handler(static function () {
        });
        $isValid = @preg_match($value, '') !== false;
        restore_error_handler();

        return $isValid;
    }

    private function isValidDomain(string $domain): bool
    {
        // no leading / trailing dot
        if ($domain[0] === '.' || str_ends_with($domain, '.')) {
            return false;
        }

        // no double dots
        if (str_contains($domain, '..')) {
            return false;
        }

        $labels = explode('.', $domain);

        // Must have at least domain + tld
        if (count($labels) < 2) {
            return false;
        }

        if (array_any($labels, fn($label) => !$this->isValidLabel($label))) {
            return false;
        }

        // TLD rules
        $tld = end($labels);

        return ctype_alpha($tld) && strlen($tld) >= 2;
    }

    private function isValidLabel(string $label): bool
    {
        // length 1–63
        if ($label === '' || strlen($label) > 63) {
            return false;
        }

        // allowed chars
        if (!preg_match('/^[a-z0-9-]+$/', $label)) {
            return false;
        }

        // no leading / trailing hyphen
        if ($label[0] === '-' || str_ends_with($label, '-')) {
            return false;
        }

        return true;
    }
}
