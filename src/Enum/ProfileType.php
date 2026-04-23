<?php

declare(strict_types=1);

namespace App\Enum;

enum ProfileType: string
{
    case WPA2 = 'WPA2';
    case WPA3 = 'WPA3';
}
