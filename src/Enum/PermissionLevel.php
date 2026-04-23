<?php

declare(strict_types=1);

namespace App\Enum;

enum PermissionLevel: string
{
    case NONE  = 'NONE';
    case READ  = 'READ';
    case WRITE = 'WRITE';
}
