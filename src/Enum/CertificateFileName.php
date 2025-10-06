<?php

namespace App\Enum;

enum CertificateFileName: string
{
    case CLIENT_PEM = 'client.pem';
    case KEY_PEM = 'key.pem';
}
