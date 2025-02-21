<?php

namespace App\Enum\GeoLocation;

enum IsEEAHandler: int
{
    case NOT_IN_EEA = 0;
    case IN_EEA = 1;
    case MISSING_IP = 2;
    case LOCATION_ERROR = 3;
    case MISSING_FILE = 4;
    case INVALID_DB = 5;
    case GENERIC_ERROR = 6;
}
