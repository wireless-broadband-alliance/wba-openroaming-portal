<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidTrustAnchor extends Constraint
{
    public string $certField;
    public string $chainField;
    public string $rootField;

    public string $incompleteChainMessage = 'untrustedRootMessage';
    public string $untrustedRootMessage = 'untrustedRootMessage';

    public function __construct(
        string $certField,
        string $chainField,
        string $rootField,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);
        $this->certField  = $certField;
        $this->chainField = $chainField;
        $this->rootField  = $rootField;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
