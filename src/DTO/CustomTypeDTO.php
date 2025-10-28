<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CustomTypeDTO
{
    public ?string $CUSTOMER_LOGO_ENABLED = null;

    #[Assert\File(
        mimeTypes: ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'],
        mimeTypesMessage: 'uploadValidFormat'
    )]
    public ?UploadedFile $CUSTOMER_LOGO = null;

    #[Assert\File(
        mimeTypes: ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'],
        mimeTypesMessage: 'uploadValidFormat'
    )]
    public ?UploadedFile $OPENROAMING_LOGO = null;

    #[Assert\File(
        mimeTypes: ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'],
        mimeTypesMessage: 'uploadValidFormat'
    )]
    public ?UploadedFile $WALLPAPER_IMAGE = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $WELCOME_TEXT = null;

    public ?string $WELCOME_DESCRIPTION = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $PAGE_TITLE = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $ADDITIONAL_LABEL = null;

    #[Assert\Email(message: 'validEmailAddress')]
    #[Assert\Length(max: 320, maxMessage: 'maxCharacters')]
    #[Assert\NotBlank(message: 'emailNotEmpty')]
    public ?string $CONTACT_EMAIL = null;
}
