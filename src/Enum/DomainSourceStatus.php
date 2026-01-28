<?php

namespace App\Enum;

enum DomainSourceStatus: int
{
    case ALL = 0;
    case INACTIVE = 1;
    case ACTIVE = 2;
}
