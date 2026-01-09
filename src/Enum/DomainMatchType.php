<?php

namespace App\Enum;

enum DomainMatchType: string
{
    case EXACT = 'exact';
    case SUBDOMAIN = 'subdomain';
    case WILDCARD = 'wildcard';
}
