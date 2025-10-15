<?php

namespace App\Enum;

enum CertificateProcessStatus: int
{
    case IN_PROGRESS = 0;
    case COMPLETED = 1;
    case ABORTED  = 2;
}
