<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CertificateUploadDto
{
    #[Assert\NotBlank]
    public string $name;

    #[Assert\NotNull]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['application/x-x509-ca-cert', 'application/x-pem-file', 'text/plain'],
        mimeTypesMessage: 'Please upload a valid PEM certificate file.'
    )]
    public ?UploadedFile $client = null;

    #[Assert\NotNull]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['application/x-pem-file', 'text/plain'],
        mimeTypesMessage: 'Please upload a valid PEM key file.'
    )]
    public ?UploadedFile $key = null;
}
