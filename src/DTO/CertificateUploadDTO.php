<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

//    #[Assert\Callback] this also works, and it gives the violation on the profiler
//    public function validatePemFiles(ExecutionContextInterface $context): void
//    {
//        if ($this->client && strtolower($this->client->getClientOriginalExtension()) !== 'pem') {
//            $context->buildViolation('Client certificate must be a .pem file.')
//                ->atPath('client')
//                ->addViolation();
//        }
//
//        if ($this->key && strtolower($this->key->getClientOriginalExtension()) !== 'pem') {
//            $context->buildViolation('Private key must be a .pem file.')
//                ->atPath('key')
//                ->addViolation();
//        }
//    }

    public function __construct()
    {
        $this->client = null;
        $this->key = null;
    }

    /**
     * Return an array ready to be passed to the service for later DB insertion or storage
     */
    public function toArray(): array
    {
        return [
            'client' => $this->client,
            'key' => $this->key,
        ];
    }
}
