<?php

declare(strict_types=1);

namespace App\Enum;

enum InstallationType: string
{
    case INSTALLATION = 'installation';
    case CERTIFICATES = 'certificates';
}
