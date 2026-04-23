<?php

declare(strict_types=1);

namespace App\Enum;

enum CertificateMachineType: string
{
    case FREERADIUS = 'FREERADIUS';
    case RADSECPROXY = 'RADSECPROXY';
}
