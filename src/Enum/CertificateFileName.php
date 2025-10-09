<?php

namespace App\Enum;

enum CertificateFileName: string
{
    case CLIENT_PEM = 'client';
    case KEY_PEM = 'key';
}
