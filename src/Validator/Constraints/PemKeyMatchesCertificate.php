<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PemKeyMatchesCertificate extends Constraint
{
    public string $message = 'privateKeyDoesntMatchCertificate';

    public function __construct(
        public string $certificateField = 'client',
        public string $privateKeyField = 'key',
        array $options = [],
        ?string $groups = null,
        ?string $payload = null
    ) {
        parent::__construct($options, $groups, $payload);
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    #[\Override]
    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
