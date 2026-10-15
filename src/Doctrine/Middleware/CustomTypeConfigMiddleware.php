<?php

declare(strict_types=1);

namespace App\Doctrine\Middleware;

use App\Doctrine\Type\EncryptedPhoneNumberType;
use App\Doctrine\Type\EncryptedStringType;
use App\Service\EncryptionService;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Doctrine types are created statically, so they cannot receive services by injection.
 * This middleware runs when the DB connection is created and hands them the vault-backed key.
 *
 * OpenRoaming only uses ONE vault secret: ENCRYPTION_KEY.
 */
readonly class CustomTypeConfigMiddleware implements Middleware
{
    public function __construct(
        private EncryptionService $encryptionService,
        #[Autowire(env: 'ENCRYPTION_KEY')] private string $encryptionKey,
    ) {
    }

    /**
     * @throws TypesException
     */
    public function wrap(Driver $driver): Driver
    {
        if (Type::hasType(EncryptedStringType::NAME)) {
            /** @var EncryptedStringType $type */
            $type = Type::getType(EncryptedStringType::NAME);
            $type->setEncryptionService($this->encryptionService);
        }

        if (Type::hasType(EncryptedPhoneNumberType::NAME)) {
            /** @var EncryptedPhoneNumberType $type */
            $type = Type::getType(EncryptedPhoneNumberType::NAME);
            $type->setSecretKey($this->encryptionKey);
        }

        return $driver;
    }
}
