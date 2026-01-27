<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class CloudflareDTO
{

    /**
     * Notices/Warnings generated during the upload
     * @var list<string>
     */
    public array $notices = [];

    #[NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'maxCharacters'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9.-]+$/',
        message: 'invalidFormat'
    )]
    public ?string $host = null;

    #[NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(
        min: 20,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $token = null;

    #[NotBlank(message: 'nullCA')]
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
}