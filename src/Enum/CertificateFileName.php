<?php

namespace App\Enum;

enum CertificateFileName: string
{
    // Radsecproxy required files
    case CLIENT_PEM = 'client';
    case KEY_PEM = 'key';

    // Freeradius required files
    case CA_PEM = 'ca';
    case CERT_PEM = 'cert';
    case CHAIN_PEM = 'chain';
    case FULL_CHAIN_PEM = 'full_chain';
    case PRIVATE_KEY_PEM = 'private_key';
}
