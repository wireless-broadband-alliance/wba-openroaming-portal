<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

// Class level so this validator can access the client/key at the same time
#[CustomAssert\PemKeyMatchesCertificate(
    certificateField: 'cert',
    privateKeyField: 'privKey'
)]
#[CustomAssert\ValidCertificateChain(
    certField: 'cert',
    chainField: 'chain',
)]
#[CustomAssert\ValidTrustAnchor(
    certField: 'cert',
    chainField: 'chain',
    rootField: 'ca'
)]
class CertificateFreeradiusUploadManualDTO
{
    public array $notices = [];

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-x509-ca-cert',
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'nullCA',
        mimeTypesMessage: 'invalidFileTypeCA'
    )]
    #[CustomAssert\ValidPemCertificate]
    #[CustomAssert\ValidRsaCertificate]
    public ?UploadedFile $ca = null;

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
    #[CustomAssert\IsLetsEncryptCertificate]
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
