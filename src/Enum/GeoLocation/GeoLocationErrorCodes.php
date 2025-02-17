<?php

namespace App\Enum\GeoLocation;

enum GeoLocationErrorCodes: string
{
    case MISSING_FILE = 'MISSING_FILE';
    case INVALID_DB = 'INVALID_DB';
    case GENERIC_ERROR = 'GENERIC_ERROR';
    case IN_EU = 'IN_EU';
    case NOT_IN_EU = 'NOT_IN_EU';
}
