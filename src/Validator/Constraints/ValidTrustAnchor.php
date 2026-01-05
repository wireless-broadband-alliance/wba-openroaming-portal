<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidTrustAnchor extends Constraint
{
    public string $invalidCertificateMessage = 'invalidCertificateType';
    public string $incompleteChainMessage = 'incompleteChainMessage';
    public string $untrustedRootMessage = 'untrustedRootMessage';

    public string $certField;
    public string $chainField;
    public string $rootField;

    public function __construct(
        string $certField,
        string $chainField,
        string $rootField,
        ?array $groups = null,
        mixed $payload = null
    ) {
        $this->certField = $certField;
        $this->chainField = $chainField;
        $this->rootField = $rootField;

        parent::__construct([], $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
