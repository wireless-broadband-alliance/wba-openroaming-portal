<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class CertificateFreeradiusUploadDTO
{
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
    #[CustomAssert\ValidPemPrivateKey]
    public ?UploadedFile $privKey = null;
}
