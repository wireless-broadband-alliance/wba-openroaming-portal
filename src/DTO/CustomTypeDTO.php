<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CustomTypeDTO
{
    public ?string $CUSTOMER_LOGO_ENABLED = null;

    public ?UploadedFile $CUSTOMER_LOGO = null;

    public ?UploadedFile $OPENROAMING_LOGO = null;

    public ?UploadedFile $WALLPAPER_IMAGE = null;

    public ?string $WELCOME_TEXT = null;

    public ?string $WELCOME_DESCRIPTION = null;

    public ?string $PAGE_TITLE = null;

    public ?string $ADDITIONAL_LABEL = null;

    public ?string $CONTACT_EMAIL = null;


}