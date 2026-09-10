<?php

declare(strict_types=1);

namespace App\Exception;

use Exception;

class EncryptionException extends Exception
{
    public function errorMessage(): string
    {
        return $this->getMessage() !== '' ? $this->getMessage() : 'Encryption Error';
    }
}
