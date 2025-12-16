<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidTrustAnchor extends Constraint
{
    public string $incompleteChainMessage = 'untrustedRootMessage';
    public string $untrustedRootMessage = 'untrustedRootMessage';

    public function __construct(
        public string $certField,
        public string $chainField,
        public string $rootField,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
