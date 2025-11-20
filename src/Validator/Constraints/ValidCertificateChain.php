<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidCertificateChain extends Constraint
{
    public string $message = 'invalidCertificateChain';

    public function __construct(
        public string $certField,
        public string $chainField,
        array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($groups, $payload);
    }

    /**
     * This ensures Symfony knows this is a **class-level constraint**.
     */
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
