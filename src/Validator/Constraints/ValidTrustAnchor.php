<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidTrustAnchor extends Constraint
{
    public string $invalidCertificateMessage = 'invalidCertificateType';
    public string $incompleteChainMessage = 'incompleteChainMessage';
    public string $untrustedRootMessage = 'untrustedRootMessage';

    public function __construct(
        public string $certField,
        public string $chainField,
        public string $rootField,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct(
            groups: $groups,
            payload: $payload
        );
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
