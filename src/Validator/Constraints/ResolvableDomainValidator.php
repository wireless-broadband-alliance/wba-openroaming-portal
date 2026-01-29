<?php

namespace App\Validator\Constraints;

use App\DTO\DomainBlacklistAddDTO;
use App\Enum\DomainMatchType;
use App\Service\DomainDnsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ResolvableDomainValidator extends ConstraintValidator
{
    public function __construct(
        private DomainDnsResolver $resolver
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof DomainBlacklistAddDTO) {
            return;
        }

        if ($value->input === null || $value->matchType === null) {
            return;
        }

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