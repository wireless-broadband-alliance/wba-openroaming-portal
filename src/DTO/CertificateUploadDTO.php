<?php

namespace App\DTO;

use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CertificateUploadDTO
{
    public ?CertificateFileName $name = null;
    public ?CertificateMachineType $type = null;

    #[Assert\NotNull(message: 'Please upload a client certificate file.')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-x509-ca-cert',
            'application/x-pem-file',
            'application/octet-stream', // some .pem files are detected like this because of the browser differences
            'text/plain'
        ],
        mimeTypesMessage: 'Invalid client certificate file type. Please upload a valid .pem certificate.'
    )]
    public ?UploadedFile $client = null;

    #[Assert\NotNull(message: 'Please upload a private key file.')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain'
        ],
        mimeTypesMessage: 'Invalid private key file type. Please upload a valid .pem key.'
    )]
    public ?UploadedFile $key = null;
}
