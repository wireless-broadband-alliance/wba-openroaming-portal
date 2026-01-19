<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudflareDTO
{

    public ?string $host = null;

    public ?string $token = null;

    public ?string $port = null;

    #[Assert\File(
        mimeTypes: ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'],
        mimeTypesMessage: 'uploadValidFormat'
    )]
    public ?UploadedFile $caCert = null;
}