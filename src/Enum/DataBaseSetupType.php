<?php

namespace App\Enum;

enum DataBaseSetupType: string
{
    case DATABASE_URL = 'DATABASE_URL';
    case DATABASE_FREERADIUS_URL = 'DATABASE_FREERADIUS_URL';
}
