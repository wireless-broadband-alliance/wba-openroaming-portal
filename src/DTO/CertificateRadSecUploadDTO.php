<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;
use Symfony\Component\Validator\Constraints\NotBlank;

#[CustomAssert\PemKeyMatchesCertificate(
    certificateField: 'client',
    privateKeyField: 'key'
)] // Class level so this validator can access the client/key at the same time
class CertificateRadSecUploadDTO
{
    #[NotBlank(message: 'nullCertificate')]
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
    #[CustomAssert\ValidWbaChain]
    public ?UploadedFile $client = null;

    #[NotBlank(message: 'nullKey')]
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
