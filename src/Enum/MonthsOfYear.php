<?php

namespace App\Enum;

enum MonthsOfYear: int
{
    case january = 1;
    case february = 2;
    case march = 3;
    case april = 4;
    case may = 5;
    case june = 6;
    case july = 7;
    case august = 8;
    case september = 9;
    case october = 10;
    case november = 11;
    case december = 12;

    /**
     * Returns an array for ChoiceType.
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->name] = $case->value;
        }
        return $choices;
    }
}
