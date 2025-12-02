<?php

namespace App\Enum;

enum InstallationType: string
{
    case INSTALLATION = 'installation';
    case CERTIFICATES = 'certificates';
}