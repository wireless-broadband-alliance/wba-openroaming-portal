<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class CronFrequencyWarning extends Constraint
{
    public string $message = 'Warning: saving in simple mode will remove the {{ frequency }} frequency settings for this cron.';

    #[\Override]
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
