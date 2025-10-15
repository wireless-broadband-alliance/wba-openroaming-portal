<?php

namespace App\Enum;

enum InstallationProgressType: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case ABORTED = 'ABORTED';
}