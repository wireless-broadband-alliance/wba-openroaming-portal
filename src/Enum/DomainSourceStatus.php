<?php

namespace App\Enum;

enum DomainSourceStatus: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;

    case ALL = 2;
}
