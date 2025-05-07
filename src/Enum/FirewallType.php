<?php

namespace App\Enum;

enum FirewallType: string
{
    case LANDING = 'login';
    case DASHBOARD = 'dashboard';
}
