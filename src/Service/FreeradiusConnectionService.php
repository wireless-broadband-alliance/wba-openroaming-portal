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

    /**
     * Connect to a remote FreeRADIUS server over RadSec and fetch certificate chain
     *
     * @return array{output: string[], chain: string[]}
     */
    public function connectToServer(
        string $host,
        int $port,
        string $cert,
        string $fullchain,
        string $privKey,
        string $ca
    ): array {
        // Validate TLS files
        foreach (['cert' => $cert, 'fullchain' => $fullchain, 'privKey' => $privKey, 'ca' => $ca] as $name => $path) {
            if (!file_exists($path) || !is_readable($path) || filesize($path) === 0) {
                throw new RuntimeException("TLS {$name} file is missing, unreadable, or empty: {$path}");
            }
        }

        // Build radsecclient command
        $cmd = sprintf(
            'echo %s | radsecclient -x %s:%d auth ' .
            '-c %s -k %s -a %s 2>&1',
            escapeshellarg("User-Name = \"test\"\nUser-Password = \"testing123\""),
            escapeshellarg($host),
            $port,
            escapeshellarg($cert),
            escapeshellarg($privKey),
            escapeshellarg($ca)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                "RadSec handshake failed with {$host}:{$port}. Output: " . implode("\n", $output)
            );
        }

        // Parse certificate chain from radclient output
        $certChain = [];
        $currentCert = '';
        $insideCert = false;

        foreach ($output as $line) {
            if (str_contains($line, "-----BEGIN CERTIFICATE-----")) {
                $insideCert = true;
                $currentCert = $line . "\n";
            } elseif (str_contains($line, "-----END CERTIFICATE-----")) {
                $currentCert .= $line . "\n";
                $certChain[] = $currentCert;
                $currentCert = '';
                $insideCert = false;
            } elseif ($insideCert) {
                $currentCert .= $line . "\n";
            }
        }

        if (empty($certChain)) {
            throw new FreeradiusTestException(
                "No certificates returned by radclient. Output:\n" . implode("\n", $output)
            );
        }

        return [
            'output' => $output,
            'chain' => $certChain,
        ];
    }

    /**
     * Validate the server certificate chain against the local signing keys
     */
    public function validate(array $serverChain, array $localSigningKeys): void
    {
        if (empty($serverChain)) {
            throw FreeradiusTestException::noCertificateProvided(
                "The server did not provide any certificate during the handshake."
            );
        }

        $localHashes = array_map(static function ($path) {
            $pem = file_get_contents($path);
            $der = base64_decode(preg_replace('#-----.*?-----#', '', $pem));
            return strtolower(hash('sha256', $der));
        }, $localSigningKeys);

        $now = time();
        $validated = false;

        foreach ($serverChain as $index => $cert) {
            $certInfo = openssl_x509_parse($cert);
            if ($certInfo !== false) {
                $subject = $certInfo['name'] ?? 'Unknown';
                $validFrom = $certInfo['validFrom_time_t'] ?? 0;
                $validTo = $certInfo['validTo_time_t'] ?? 0;

                if ($now < $validFrom) {
                    $validFromDate = date('Y-m-d H:i:s', $validFrom);
                    throw FreeradiusTestException::certificateNotYetValid(
                        $subject,
                        $validFromDate,
                        "Certificate for {$subject} is not yet valid. Valid from {$validFromDate}."
                    );
                }

                if ($now > $validTo) {
                    $expiryDate = date('Y-m-d H:i:s', $validTo);
                    throw FreeradiusTestException::certificateExpired(
                        $subject,
                        $expiryDate,
                        "Certificate expired for {$subject} since {$expiryDate}."
                    );
                }
            } elseif ($index === 0) {
                throw FreeradiusTestException::invalidCertificateChain(
                    "The certificate chain provided by the server is invalid or incomplete."
                );
            }

            if (openssl_x509_export($cert, $out)) {
                $der = base64_decode(preg_replace('#-----.*?-----#', '', $out));
                $hash = strtolower(hash('sha256', $der));

                if (in_array($hash, $localHashes, true)) {
                    $validated = true;
                    break;
                }
            }
        }

        if (!$validated) {
            throw FreeradiusTestException::untrustedCertificate(
                "TLS handshake succeeded, but the certificate chain is not trusted by our CA bundle."
            );
        }
    }
}
