<?php

namespace App\Enum;

enum CertificateFileName: string
{
    // Radsecproxy references
    case CLIENT_PEM = 'Client';
    case KEY_PEM = 'Key';

    // Freeradius references
    case CA_PEM = 'CA';
    case CERT_PEM = 'Cert';
    case CHAIN_PEM = 'Chain';
    case FULL_CHAIN_PEM = 'Full Chain';
    case PRIVATE_KEY_PEM = 'Private Key';


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
