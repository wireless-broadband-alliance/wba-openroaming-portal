<?php

namespace App\Form\Transformer;

use DateTime;
use Symfony\Component\Form\DataTransformerInterface;

class BooleanToDateTimeTransformer implements DataTransformerInterface
{
    public function transform($value): bool
    {
        // Transform the DateTime value into a boolean (to displaying the checkbox "Toggle")
        return $value instanceof DateTime;
    }

    public function reverseTransform($value): ?DateTime
    {
        // Transform the boolean value from the checkbox into a DateTime or null
        return $value ? new DateTime() : null;
    }
}
