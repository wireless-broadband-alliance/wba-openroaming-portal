<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRadiusProfileStatus: int
{
    case ACTIVE = 1;
    case REVOKED = 2;
}
