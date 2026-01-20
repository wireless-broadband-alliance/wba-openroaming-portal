<?php

namespace App\Exception;

use RuntimeException;
use Throwable;

class FreeradiusTestException extends RuntimeException
{
    private array $context;

    public function __construct(string $message, array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    // Getter para contexto
    public function getContext(): array
    {
        return $this->context;
    }

    // Factory method: TLS handshake failed
    public static function tlsHandshakeFailed(string $host, int $port, int $errno, string $errstr): self
    {
        return new self(
            "TLS Handshake failed with {$host}:{$port}",
            [
                'host' => $host,
                'port' => $port,
                'errno' => $errno,
                'errstr' => $errstr
            ]
        );
    }

    // Factory method: untrusted certificate
    public static function untrustedCertificate(): self
    {
        return new self(
            "TLS handshake succeeded but certificate chain is NOT trusted",
            []
        );
    }

    // Generic custom error if need it for this test
    public static function generic(string $message, array $context = []): self
    {
        return new self($message, $context);
    }
}
