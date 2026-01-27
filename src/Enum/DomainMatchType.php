<?php

namespace App\Enum;

enum DomainMatchType: string
{
    case EXACT = '0';
    case SUBDOMAIN = '1';
}
