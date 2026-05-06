<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;
use Symfony\Component\Validator\Constraints\NotBlank;

// Class level so this validator can access the client/key at the same time
#[CustomAssert\PemKeyMatchesCertificate(
    certificateField: 'cert',
    privateKeyField: 'privKey'
)]
#[CustomAssert\ValidCertificateChain(
    certField: 'cert',
    chainField: 'chain',
)]
class CertificateFreeradiusUploadManualDTO
{
    /**
     * Notices/Warnings generated during the upload
     * @var list<string>
     */
    public array $notices = [];


    #[NotBlank(message: 'nullCert')]
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
    public ?UploadedFile $privKey = null;
}
