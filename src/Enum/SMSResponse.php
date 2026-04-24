<?php

declare(strict_types=1);

namespace App\Enum;

enum SMSResponse: string
{
    case SMS_SUCCESS_LINK = 'SMS_SUCCESS_LINK';
    case SMS_SUCCESS_CODE = 'SMS_SUCCESS_CODE';
}
