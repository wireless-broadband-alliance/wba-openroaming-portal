<?php

declare(strict_types=1);

namespace App\Enum;

enum CertificateTestResult: int
{
    case PASSED = 1;
    case FAILED = 0;
}
