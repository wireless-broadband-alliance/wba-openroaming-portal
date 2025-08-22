<?php

namespace App\Enum;

enum SMSResponse: string
{
    case SMS_SUCCESS = 'SMS_SUCCESS';
    case SMS_INVALID_MESSAGE_LENGTH = 'SMS_INVALID_MESSAGE_LENGTH';
}