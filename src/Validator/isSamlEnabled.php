<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 *
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class isSamlEnabled extends Constraint
{
    /**
     * @var string
     */
    public string $message = 'No active SAML provider found.';

    public function __construct(string $message = null, array $groups = null, mixed $payload = null)
    {
        parent::__construct(groups: $groups, payload: $payload);

        if ($message) {
            $this->message = $message;
        }
    }
}
