<?php

namespace App\Enum;

enum DomainMatchType: string
{
    case EXACT = '1';
    case SUBDOMAIN = '2';
    case WILDCARD = '3';
}
