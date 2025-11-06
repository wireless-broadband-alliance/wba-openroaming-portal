<?php

namespace App\Enum;

enum CertificateTestResult: int
{
    case PASSED = 0;
    case FAILED = 1;
}
