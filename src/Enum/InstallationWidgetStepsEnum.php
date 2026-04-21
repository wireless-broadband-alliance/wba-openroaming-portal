<?php

declare(strict_types=1);

namespace App\Enum;

enum InstallationWidgetStepsEnum: string
{
    case DATABASE = 'database';
    case SETTINGS = 'settings';
    case ADMIN_CREDENTIALS = 'admin_credentials';
    case SUMMARY = 'summary';
}
