<?php

namespace App\Enum;

enum DomainSourceStatus: string
{
    case ALL = 'all';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
