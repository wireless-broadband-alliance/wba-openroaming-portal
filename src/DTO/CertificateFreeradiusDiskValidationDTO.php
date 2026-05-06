<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validator\Constraints as CustomAssert;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[CustomAssert\ValidCertificateChain(
    certField: 'cert',
    chainField: 'chain',
)]
class CertificateFreeradiusDiskValidationDTO
{
    // This file is exactly the same of CertificateFreeradiusValidationDTO, but without the privkey
    /**
     * Notices/Warnings generated during the validation
     * @var list<string>
     */
    public array $notices = [];

    #[Assert\NotBlank(message: 'nullCert')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'nullCert',
        mimeTypesMessage: 'invalidFileTypeCert'
    )]
    #[CustomAssert\ValidPemCertificate]
    #[CustomAssert\ValidRsaCertificate]
    #[CustomAssert\WarnIfNotEvCertificate]
    public ?UploadedFile $cert = null;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'nullChain',
        mimeTypesMessage: 'invalidFileTypeChain'
    )]
    #[CustomAssert\ValidPemCertificate]
    #[CustomAssert\ValidRsaCertificate]
    public ?UploadedFile $chain = null;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'nullFullChain',
        mimeTypesMessage: 'invalidFileTypeFullChain'
    )]
    #[CustomAssert\ValidPemCertificate]
    #[CustomAssert\ValidRsaCertificate]
    public ?UploadedFile $fullChain = null;
}