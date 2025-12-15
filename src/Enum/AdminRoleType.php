<?php

namespace App\Enum;

enum AdminRoleType: string
{
    case ROLE_ADMIN = 'ROLE_ADMIN';
    case ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
}
