<?php

namespace App\Enum;

enum CertificateMachineType: string
{
    case FREERADIUS = 'FREERADIUS';
    case RADSECPROXY = 'RADSECPROXY';
}
