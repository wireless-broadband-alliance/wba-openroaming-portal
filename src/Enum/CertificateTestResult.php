<?php

namespace App\Enum;

enum CertificateTestResult: int
{
    case TESTED = 0;
    case SKIPPED = 1;
    case FAILED = 2;
}
