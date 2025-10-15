<?php

namespace App\Enum;

enum InstallationStep: string
{
    case DATABASE = 'DATABASE';
    CASE SETTINGS = 'SETTINGS';
    CASE JWT = 'JWT';
    CASE ADMIN = 'ADMIN';
    case COMPLETED = 'COMPLETED';

}