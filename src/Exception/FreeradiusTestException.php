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
            "TLS Handshake failed with {$host}:{$port} | {$errstr}",
            [
                'host' => $host,
                'port' => $port,
                'errno' => $errno,
                'errstr' => $errstr
            ]
        );
    }

    // Factory method: untrusted certificate
    public static function untrustedCertificate(?string $customMessage = null): self
    {
        return new self(
            $customMessage ?? "TLS handshake succeeded but certificate chain is NOT trusted",
            []
        );
    }

    public static function certificateExpired(string $subject, string $expiryDate, ?string $customMessage = null): self
    {
        return new self(
            $customMessage ?? "Certificate expired for {$subject} since {$expiryDate}",
            [
                'subject' => $subject,
                'expiryDate' => $expiryDate,
            ]
        );
    }

    public static function certificateNotYetValid(string $subject, string $validFrom, ?string $customMessage = null): self
    {
        return new self(
            $customMessage ?? "Certificate for {$subject} is not yet valid. Valid from {$validFrom}",
            [
                'subject' => $subject,
                'validFrom' => $validFrom,
            ]
        );
    }

    public static function noCertificateProvided(?string $customMessage = null): self
    {
        return new self(
            $customMessage ?? "Server did not provide any certificate",
            []
        );
    }

    public static function invalidCertificateChain(?string $customMessage = null): self
    {
        return new self(
            $customMessage ?? "Certificate chain is invalid or incomplete",
            []
        );
    }

    // Generic custom error if need it for this test
    public static function generic(string $message, array $context = []): self
    {
        return new self($message, $context);
    }
}
