<?php

namespace App\Service;

use App\Exception\FreeradiusTestException;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Throwable;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class FreeradiusConnectionService
{
    private Connection $freeradiusConnection;

    public function __construct(
        ManagerRegistry $doctrine,
        private TranslatorInterface $translator
    ) {
        $connection = $doctrine->getConnection('freeradius');

        if (!$connection instanceof Connection) {
            throw new RuntimeException(
                $this->translator->trans(
                    'invalidConnectionType',
                    [],
                    'FreeradiusConnectionService'
                )
            );
        }

        $this->freeradiusConnection = $connection;
    }

    /**
     * Check the FreeRADIUS database connection
     *
     * @return array{success: bool, message: string}
     */
    public function checkDBConnection(): array
    {
        try {
            $this->freeradiusConnection->executeQuery('SELECT 1');
            return [
                'success' => true,
                'message' => $this->translator->trans(
                    'freeRADIUSConnectionSuccessfully',
                    [],
                    'FreeradiusConnectionService'
                ),
            ];
        } catch (Throwable) {
            return [
                'success' => false,
                'message' => $this->translator->trans(
                    'FreeRADIUSConnectionFailed',
                    [],
                    'FreeradiusConnectionService'
                ),
            ];
        }
    }

    public function connectToServer(
        string $host,
        int $port,
        string $cert,
        string $fullchain,
        string $privKey,
        string $ca
    ): array {
        // Validate TLS files early (fail fast)
        foreach (
            [
                'cert' => $cert,
                'fullchain' => $fullchain,
                'privKey' => $privKey,
                'ca' => $ca,
            ] as $name => $path
        ) {
            if (!file_exists($path)) {
                throw new RuntimeException("TLS {$name} file not found: {$path}");
            }
            if (!is_readable($path)) {
                throw new RuntimeException("TLS {$name} file not readable: {$path}");
            }
            if (filesize($path) === 0) {
                throw new RuntimeException("TLS {$name} file is empty: {$path}");
            }
        }

        // Create stream context (ONLY ONCE)
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'capture_peer_cert_chain' => true,
                'local_cert' => $cert,
                'local_pk' => $privKey,
                'cafile' => $ca,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            ],
        ]);

        if (!is_resource($context)) {
            throw new RuntimeException('Failed to create TLS stream context');
        }

        try {
            $connection = stream_socket_client(
                "tls://{$host}:{$port}",
                $errno,
                $errstr,
                15,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } catch (Throwable $e) {
            // Collect OpenSSL error stack
            $opensslErrors = [];
            while ($err = openssl_error_string()) {
                $opensslErrors[] = $err;
            }

            throw FreeradiusTestException::tlsHandshakeFailed(
                $host,
                $port,
                $errno ?? 0,
                ($errstr ?: $e->getMessage()) .
                (!empty($opensslErrors) ? ' | OpenSSL errors: ' . implode(' ; ', $opensslErrors) : '')
            );
        }

        // The ONLY real failure condition
        if ($connection === false) {
            throw FreeradiusTestException::tlsHandshakeFailed(
                $host,
                $port,
                $errno ?? 0,
                $errstr ?? ''
            );
        }

        // Extract peer certificates
        $params = stream_context_get_params($connection);

        $chain = $params['options']['ssl']['peer_certificate_chain'] ?? [];
        $leaf = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($leaf !== null) {
            array_unshift($chain, $leaf);
        }

        return [
            'connection' => $connection,
            'chain' => $chain,
        ];
    }

    public function validate(array $serverChain, array $localSigningKeys): void
    {
        // Gets the current hashes from the server certificates
        $localHashes = array_map(static function ($path) {
            $pem = file_get_contents($path);
            $der = base64_decode(preg_replace('#-----.*?-----#', '', $pem));
            return strtolower(hash('sha256', $der));
        }, $localSigningKeys);

        // Checks if the certs from the server are the same from the portal
        $validated = false;
        foreach ($serverChain as $cert) {
            $pem = openssl_x509_export($cert, $out) ? $out : null;
            if (!$pem) {
                continue;
            }

            $der = base64_decode(preg_replace('#-----.*?-----#', '', $pem));
            $hash = strtolower(hash('sha256', $der));

            if (in_array($hash, $localHashes, true)) {
                $validated = true;
                break;
            }
        }

        if (!$validated) {
            throw FreeradiusTestException::untrustedCertificate();
        }
    }
}
