<?php

namespace App\Enum;

enum InstallationWidgetStepsEnum: string
{
    case DATABASE = 'DATABASE';
    case ADMIN_CONFIGURATION = 'ADMIN_CONFIGURATION';
    case RECAP = 'RECAP';
    case DONE = 'DONE';
}
