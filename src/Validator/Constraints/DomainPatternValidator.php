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
            $domain = substr($value, 2);

            $asciiDomain = idn_to_ascii(
                $domain,
                IDNA_DEFAULT,
                INTL_IDNA_VARIANT_UTS46
            );

            if ($asciiDomain !== false && $this->isValidDomain($asciiDomain)) {
                return;
            }

            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }
        $asciiValue = idn_to_ascii(
            $value,
            IDNA_DEFAULT,
            INTL_IDNA_VARIANT_UTS46
        );

        if ($asciiValue === false) {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        if ($this->isValidDomain($asciiValue)) {
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

        return @preg_match($value, '') !== false;
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

        $tld = array_pop($labels);

        // TLD: letters OR punycode
        if (
            strlen($tld) < 2 ||
            (!ctype_alpha($tld) && !str_starts_with($tld, 'xn--'))
        ) {
            return false;
        }

        foreach ($labels as $label) {
            if ($label === '*') {
                continue; // wildcard allowed
            }
            if (!$this->isValidLabel($label)) {
                return false;
            }
        }

        return true;
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
        return $label[0] !== '-' && !str_ends_with($label, '-');
    }
}
