<?php

namespace App\Enum;

enum CertificateTestResult: int
{
    case PASSED = 1;
    case FAILED = 0;
}
