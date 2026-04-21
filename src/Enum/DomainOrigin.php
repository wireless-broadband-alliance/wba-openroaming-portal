<?php

declare(strict_types=1);

namespace App\Enum;

enum DomainOrigin: int
{
    case LINK = 0;
    case MANUAL = 1;
    case DELETED = 2;
}
