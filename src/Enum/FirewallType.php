<?php

declare(strict_types=1);

namespace App\Enum;

enum FirewallType: string
{
    case LANDING = 'landing';
    case DASHBOARD = 'dashboard';
}
