<?php

namespace App\Enum;

enum ProcessStatusType: int
{
    case STARTED = 0;
    case IN_PROGRESS = 1;
    case COMPLETED = 2;
    case ABORTED  = 3;
}
