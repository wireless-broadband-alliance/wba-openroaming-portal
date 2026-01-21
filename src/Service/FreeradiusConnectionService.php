<?php

namespace App\Service;

use App\Exception\FreeradiusTestException;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

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

//nuke the buffer
        while (openssl_error_string() !== false);

        try {
            $connection = @stream_socket_client(
                "tls://{$host}:{$port}",
                $errno,
                $errstr,
                15,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($connection === false) {
                $opensslErrors = [];
                while ($err = openssl_error_string()) {
                    $opensslErrors[] = $err;
                }
                
                // Filter out misleading success if the connection failed
                if (trim($errstr) === 'SSL: success' || empty(trim($errstr))) {
                    $errstr = 'TLS connection failed';
                }

                $details = $errstr .
                    (!empty($opensslErrors) ? ' | OpenSSL errors: ' . implode(' ; ', $opensslErrors) : '');
                
                throw FreeradiusTestException::tlsHandshakeFailed($host, $port, $errno ?: 0, $details);
            }
        } catch (Throwable $e) {
            if ($e instanceof FreeradiusTestException) {
                throw $e;
            }


            $opensslErrors = [];
            while ($err = openssl_error_string()) {
                $opensslErrors[] = $err;
            }

            if (trim($errstr) === 'SSL: success' || empty(trim($errstr))) {
                $errstr = $e->getMessage();
            }

            $details = $errstr .
                (!empty($opensslErrors) ? ' | OpenSSL errors: ' . implode(' ; ', $opensslErrors) : '');

            throw FreeradiusTestException::tlsHandshakeFailed(
                $host,
                $port,
                $errno ?? 0,
                $details
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
        if (empty($serverChain)) {
            throw FreeradiusTestException::noCertificateProvided("The server did not provide any certificate during the handshake.");
        }

        // Gets the current hashes from the server certificates
        $localHashes = array_map(static function ($path) {
            $pem = file_get_contents($path);
            $der = base64_decode(preg_replace('#-----.*?-----#', '', $pem));
            return strtolower(hash('sha256', $der));
        }, $localSigningKeys);

        $now = time();
        $validated = false;

        foreach ($serverChain as $index => $cert) {
            // Parse certificate details
            $certInfo = openssl_x509_parse($cert);
            if ($certInfo !== false) {
                $subject = $certInfo['name'] ?? 'Unknown';
                $validTo = $certInfo['validTo_time_t'] ?? 0;
                $validFrom = $certInfo['validFrom_time_t'] ?? 0;

                if ($now > $validTo) {
                    $expiryDate = date('Y-m-d H:i:s', $validTo);
                    throw FreeradiusTestException::certificateExpired(
                        $subject,
                        $expiryDate,
                        "Certificate expired for {$subject} since {$expiryDate}."
                    );
                }

                if ($now < $validFrom) {
                    $validFromDate = date('Y-m-d H:i:s', $validFrom);
                    throw FreeradiusTestException::certificateNotYetValid(
                        $subject,
                        $validFromDate,
                        "Certificate for {$subject} is not yet valid. Valid from {$validFromDate}."
                    );
                }
            } elseif ($index === 0) {
                // If the leaf certificate cannot be parsed, the chain is definitely invalid
                throw FreeradiusTestException::invalidCertificateChain("The certificate chain provided by the server is invalid or incomplete.");
            }

            // Trust match logic
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
            throw FreeradiusTestException::untrustedCertificate("The TLS handshake was successful, but the certificate chain presented by the server is not trusted by our CA bundle.");
        }
    }
}
