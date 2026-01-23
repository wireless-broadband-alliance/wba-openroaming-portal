<?php

namespace App\Enum;

enum DomainOrigin: string
{
    case LINK = '0';
    case MANUAL = '1';
    case UNKNOWN = '2';
    case DELETED = '3';
}
