<?php

namespace App\Enum;

enum TwoFAType: string
{
    case NOT_ENFORCED = "NOT_ENFORCED";
    case ENFORCED_FOR_LOCAL = "ENFORCED_FOR_LOCAL";
    case ENFORCED_FOR_ALL = "ENFORCED_FOR_ALL";
}
