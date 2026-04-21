<?php

declare(strict_types=1);

namespace App\Enum;

enum ProcessStatusType: int
{
    case IN_PROGRESS = 0;
    case COMPLETED = 1;
    case ABORTED  = 2;
}
