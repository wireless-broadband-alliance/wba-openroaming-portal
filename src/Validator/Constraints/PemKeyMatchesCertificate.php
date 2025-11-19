<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PemKeyMatchesCertificate extends Constraint
{
    public string $message = 'privateKeyDoesntMatchCertificate';
    public string $certificateField;
    public string $privateKeyField;

    public function __construct(
        string $certificateField = 'client',
        string $privateKeyField = 'key',
        array $options = [],
        ?string $groups = null,
        ?string $payload = null
    ) {
        $this->certificateField = $certificateField;
        $this->privateKeyField = $privateKeyField;

        parent::__construct($options, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
