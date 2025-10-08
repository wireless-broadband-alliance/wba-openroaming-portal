<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CertificateUploadDTO
{
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-x509-ca-cert',
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'Please upload a client certificate file.',
        mimeTypesMessage: 'Invalid client certificate file type. Please upload a valid .pem certificate.'
    )]
    public ?UploadedFile $client = null;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/x-pem-file',
            'application/octet-stream',
            'text/plain',
        ],
        notFoundMessage: 'Please upload a private key file.',
        mimeTypesMessage: 'Invalid private key file type. Please upload a valid .pem key.'
    )]
    public ?UploadedFile $key = null;


    public function __construct()
    {
        $this->client = null;
        $this->key = null;
    }

    /**
     * Return an array ready to be passed to your service for DB insertion or storage
     */
    public function toArray(): array
    {
        return [
            'client' => $this->client,
            'key' => $this->key,
        ];
    }
}
