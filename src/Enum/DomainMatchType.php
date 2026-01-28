<?php

namespace App\Enum;

enum DomainMatchType: int
{
    case EXACT = 0;
    case SUBDOMAIN = 1;
}
