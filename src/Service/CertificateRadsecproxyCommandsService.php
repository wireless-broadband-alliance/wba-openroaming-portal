<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\CertificateTestResult;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateRadsecproxyCommandsService
{
    /** @var string[] */
    private array $certDirs;

    /**
     * Remote directory that holds the final radsecproxy chain.pem.
     * This is the only one of $certDirs that chain.pem actually lives in
     * (per radsecproxy.conf on the target machine).
     */
    private const string RADSECPROXY_CERTS_DIR = '~/wba-openroaming-connector/hybrid/configs/radsecproxy/certs/';

    /**
     * Filenames of the CA chain certs bundled inside this project
     * (config/radsecproxy/certs/chain/), in the exact order they must
     * appear in chain.pem. Order matters: each cert must be signed by
     * the one that follows it, so this must NOT be glob()'d/sorted
     * alphabetically - that would silently break the chain
     * (e.g. WBA_Cisco_Policy_CA sorts before WBA_Issuing_CA, but it
     * must come after it).
     *
     * When WBA renews/renames one of these, update the committed .pem
     * file(s) in config/radsecproxy/certs/chain/ AND this list together.
     *
     * @var string[]
     */
    private const array CHAIN_CA_FILES = [
        'WBA_Issuing_CA.pem',
        'WBA_Cisco_Policy_CA.pem',
        'WBA_Issuing7_CA.pem',
        'WBA_Policy7_CA.pem',
    ];

    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/config/radsecproxy/certs/chain')]
        private string $chainCertsDir,
    ) {
        // All directories where certificates must exist on the target machine
        $this->certDirs = [
            self::RADSECPROXY_CERTS_DIR,
            '~/wba-openroaming-connector/certs/wba/',
        ];
    }

    /**
     * @param array<int, array{name: string, content?: string}> $certificateSet
     * @return array<int, array{description: string, command: string}>
     */
    public function getRenewCommands(array $certificateSet): array
    {
        if ($certificateSet === []) {
            return [
                [
                    'description' => $this->translator->trans(
                        'no_active_process',
                        domain: 'CertificateRadsecCommandsService'
                    ),
                    'command' => '# No action required.',
                ]
            ];
        }

        return $this->generateCommands($certificateSet);
    }

    /**
     * @param array<int, array{name: string, content?: string}> $certificates
     * @return array<int, array{description: string, command: string}>
     */
    private function generateCommands(array $certificates): array
    {
        $commands = [];

        // Remove old certificates registrations
        $rmFiles = [];

        foreach ($this->certDirs as $dir) {
            $rmFiles[] = $dir . 'client.pem';
            $rmFiles[] = $dir . 'key.pem';
        }
        // The old chain.pem must also go - otherwise a stale end-entity cert
        // can keep being served even after client.pem/key.pem are renewed.
        $rmFiles[] = self::RADSECPROXY_CERTS_DIR . 'chain.pem';

        $commands[] = [
            'description' => $this->translator->trans(
                'remove_old_files',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'rm -f ' . implode(' ', $rmFiles),
        ];

        // Certificate filename mapping
        $radsecFileMap = [
            CertificateFileName::CLIENT_PEM->value => CertificateFileName::CLIENT_PEM_FILE->value,
            CertificateFileName::KEY_PEM->value => CertificateFileName::KEY_PEM_FILE->value,
        ];

        // Write new certificates, and keep the client.pem content around
        // since chain.pem needs to be rebuilt from it below.
        $clientPemContent = null;

        foreach ($certificates as $cert) {
            $content = $cert['content'] ?? null;

            if (!$content) {
                continue;
            }

            if ($cert['name'] === CertificateFileName::CLIENT_PEM->value) {
                $clientPemContent = $content;
            }

            $escapedContent = str_replace("'", "'\"'\"'", $content);
            $targetFile = $radsecFileMap[$cert['name']] ?? null;
            if ($targetFile === null) {
                continue;
            }

            foreach ($this->certDirs as $dir) {
                $commands[] = [
                    'description' => $this->translator->trans(
                        'write_cert_file',
                        ['%filename%' => $targetFile],
                        'CertificateRadsecCommandsService'
                    ),
                    'command' => sprintf(
                        "echo '%s' > %s%s",
                        $escapedContent,
                        $dir,
                        $targetFile
                    ),
                ];
            }
        }

        // Regenerate chain.pem: the fresh client.pem this controller just
        // received + the CA chain certs bundled in this project. We embed
        // the full content directly (same "echo > file" pattern as
        // client.pem/key.pem above) rather than `cat`-ing files on the
        // target, since the target machine isn't guaranteed to have the
        // chain CA files at all - they live in this repo, not there.
        if ($clientPemContent !== null) {
            $chainContent = rtrim($clientPemContent) . "\n" . $this->getBundledChainContent();
            $escapedChainContent = str_replace("'", "'\"'\"'", $chainContent);

            $commands[] = [
                'description' => $this->translator->trans(
                    'generate_chain_pem',
                    domain: 'CertificateRadsecCommandsService'
                ),
                'command' => sprintf(
                    "echo '%s' > %schain.pem",
                    $escapedChainContent,
                    self::RADSECPROXY_CERTS_DIR
                ),
            ];
        }

        // Navigate to project directory
        $commands[] = [
            'description' => $this->translator->trans(
                'navigate_project_directory',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'cd ~/wba-openroaming-connector/hybrid/',
        ];

        // Rebuild containers
        $commands[] = [
            'description' => $this->translator->trans(
                'rebuild_and_start_container',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'docker compose up -d --build',
        ];

        // Verify container
        $commands[] = [
            'description' => $this->translator->trans(
                'verify_container',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'docker compose ps radsecproxy',
        ];

        // Show logs
        $commands[] = [
            'description' => $this->translator->trans(
                'check_logs',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'docker compose logs --tail=50 radsecproxy',
        ];

        return $commands;
    }

    /**
     * Reads the bundled WBA CA chain certs (committed in this project) in
     * the fixed order defined by self::CHAIN_CA_FILES, and concatenates
     * them into a single PEM blob ready to be appended after client.pem.
     */
    private function getBundledChainContent(): string
    {
        $contents = [];

        foreach (self::CHAIN_CA_FILES as $filename) {
            $path = rtrim($this->chainCertsDir, '/') . '/' . $filename;

            if (!is_readable($path)) {
                throw new RuntimeException(
                    sprintf(
                        'Missing or unreadable chain CA certificate: %s',
                        $path
                    )
                );
            }

            $fileContent = file_get_contents($path);
            if ($fileContent === false) {
                throw new RuntimeException(
                    sprintf(
                        'Failed to read chain CA certificate: %s',
                        $path
                    )
                );
            }

            $contents[] = rtrim($fileContent);
        }

        return implode("\n", $contents);
    }

    /**
     * Persist the test result to the database
     */
    public function updateRadsecproxyTestResult(
        CertificateSetupProcess $process,
        CertificateTestResult $result
    ): void {
        $process->setRadsecproxyTestResult($result);
        $this->entityManager->persist($process);
        $this->entityManager->flush();
    }
}
