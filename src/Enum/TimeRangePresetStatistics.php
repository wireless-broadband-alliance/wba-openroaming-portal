<?php

declare(strict_types=1);

namespace App\Enum;

enum TimeRangePresetStatistics: string
{
    case Yesterday = 'yesterday';
    case SevenDays = '7d';
    case ThirtyDays = '30d';
    case OneMonth = '1m';
    case Custom = 'custom';

    public static function fromInput(string $value): self
    {
        return self::tryFrom($value) ?? self::Custom;
    }

    public static function default(): self
    {
        return self::SevenDays;
    }
}
