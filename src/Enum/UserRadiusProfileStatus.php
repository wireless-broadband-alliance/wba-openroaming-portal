<?php

namespace App\Enum;

enum UserRadiusProfileStatus: int
{
    case ACTIVE = 1;
    case REVOKED = 2;
    case EXPIRED = 3;
}
