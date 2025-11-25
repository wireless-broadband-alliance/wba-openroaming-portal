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


    // Radsecproxy PEM
    case CLIENT_PEM_FILE = 'client.pem';
    case KEY_PEM_FILE = 'key.pem';

    // Freeradius PEM
    case CA_PEM_FILE = 'ca.pem';
    case CERT_PEM_FILE = 'cert.pem';
    case CHAIN_PEM_FILE = 'chain.pem';
    case FULL_CHAIN_PEM_FILE = 'fullchain.pem';
    case PRIVATE_KEY_PEM_FILE = 'privkey.pem';
}
