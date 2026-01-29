<?php

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS)]
class DomainPatternMatch extends Constraint
{
    public string $exactNoWildcard = 'exact_no_wildcard';
    public string $subdomainRequiresWildcard = 'subdomain_requires_wildcard';

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}