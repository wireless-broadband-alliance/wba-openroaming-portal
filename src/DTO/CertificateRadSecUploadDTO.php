<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

#[CustomAssert\PemKeyMatchesCertificate] // Class level so this validator can access the client/key at the same time
class CertificateRadSecUploadDTO
{
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-x509-ca-cert',
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'nullCertificate',
        mimeTypesMessage: 'invalidFileTypeCert'
    )]
    #[CustomAssert\ValidPemCertificate]
    public ?UploadedFile $client = null;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'nullKey',
        mimeTypesMessage: 'invalidFileTypeKey'
    )]
    public ?UploadedFile $key = null;
}
