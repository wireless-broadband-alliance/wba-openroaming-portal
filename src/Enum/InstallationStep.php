<?php

declare(strict_types=1);

namespace App\Enum;

enum InstallationStep: string
{
    case DATABASE = 'DATABASE';
    case SETTINGS = 'SETTINGS';
    case ADMIN = 'ADMIN';
    case COMPLETED = 'COMPLETED';
    case COMMAND = 'COMMAND';
}
