<?php

declare(strict_types=1);

namespace App\Enum;

enum ApiVersion: string
{
    case API_V3 = 'API_V3';
    case API_V4 = 'API_V4';
}
