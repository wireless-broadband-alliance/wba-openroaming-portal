<?php

namespace App\Enum;

enum DomainSourceStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
