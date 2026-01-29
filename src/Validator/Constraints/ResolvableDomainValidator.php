<?php

namespace App\Validator\Constraints;

use App\DTO\DomainBlacklistAddDTO;
use App\Enum\DomainMatchType;
use App\Service\DomainDnsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ResolvableDomainValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DomainDnsResolver $resolver
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ResolvableDomain) {
            throw new UnexpectedTypeException($constraint, ResolvableDomain::class);
        }

        if (!$value instanceof DomainBlacklistAddDTO) {
            return;
        }

        if ($value->input === null || !$value->matchType instanceof DomainMatchType) {
            return;
        }

        // Ignore wildcards and regex patterns
        if ($value->input === '*' || str_starts_with($value->input, '/')) {
            return;
        }

        $domain = $value->input;

        if ($value->matchType === DomainMatchType::SUBDOMAIN) {
            $domain = substr($domain, 2); // remove "*."
        }

        // Convert IDN → ASCII
        $ascii = idn_to_ascii(
            $domain,
            IDNA_DEFAULT,
            INTL_IDNA_VARIANT_UTS46
        );

        if ($ascii === false || !$this->resolver->resolver($ascii)) {
            $this->context
                ->buildViolation($constraint->message)
                ->atPath('input')
                ->addViolation();
        }
    }
}
