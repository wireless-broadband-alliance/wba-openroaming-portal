<?php

declare(strict_types=1);

namespace App\Enum;

enum TextInputType: string
{
    case LINK = 'LINK';
    case TEXT_EDITOR = 'TEXT_EDITOR';
}
