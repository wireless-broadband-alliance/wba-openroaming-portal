<?php

declare(strict_types=1);

namespace App\Service\SMSProvider;

use App\Entity\SMSProvider;
use App\Entity\User;
use App\Service\EncryptionService;

interface SMSProviderInterface
{
    /**
     * Sends the message through this provider's own transport (query string,
     * JSON body, form-data, a signed request, whatever it actually needs) and
     * returns the raw response. Each implementation owns its own request
     * shape and auth mechanism entirely — nothing here dictates protocol.
     */
    public static function sendSMS(SMSProvider $provider, EncryptionService $encryptionService, string $message, User $user): string;

    public static function decryptValue(EncryptionService $encryptionService, string $value): string;

}
