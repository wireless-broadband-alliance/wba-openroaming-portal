<?php

namespace App\Service;

use App\Enum\DataBaseSetupType;
use App\Enum\SettingsConfigType;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class DatabaseConnectionService
{
    public function __construct(
        private ParameterBagInterface $params
    ) {
    }

    public function testDatabaseConnection(string $dbUrl): bool
    {
        try {
            $parts = parse_url($dbUrl);

            if (!$parts || !isset($parts['scheme'], $parts['host'], $parts['user'], $parts['path'])) {
                throw new \InvalidArgumentException('Invalid database URL.');
            }

            $dbname = ltrim($parts['path'], '/');

            $connectionParams = [
                'dbname' => $dbname,
                'user' => $parts['user'],
                'password' => $parts['pass'] ?? null,
                'host' => $parts['host'],
                'port' => $parts['port'] ?? null,
                'driver' => $this->getDriverFromScheme($parts['scheme']),
            ];

            $connection = DriverManager::getConnection($connectionParams);
            $connection->executeQuery('SELECT 1');

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function writeDatabaseUrlToEnv(string $url, string $type): void
    {
        $envPath = $this->params->get('kernel.project_dir') . '/.env';
        $envContent = file_get_contents($envPath);

        if ($type === DataBaseSetupType::DATABASE_URL->value) {
            $envContent = preg_replace('/^DATABASE_URL=.*$/m', '', $envContent);
            $newLine = sprintf("DATABASE_URL=\"%s\"\n", $url);
        } elseif ($type === DataBaseSetupType::DATABASE_FREERADIUS_URL->value) {
            $envContent = preg_replace('/^DATABASE_FREERADIUS_URL=.*$/m', '', $envContent);
            $newLine = sprintf("DATABASE_FREERADIUS_URL=\"%s\"\n", $url);
        } elseif ($type === SettingsConfigType::TRUSTED_PROXIES->value) {
            $envContent = preg_replace('/^TRUSTED_PROXIES=.*$/m', '', $envContent);
            $newLine = sprintf("TRUSTED_PROXIES=%s\n", $url);
        } elseif ($type === SettingsConfigType::TURNSTILE_KEY->value) {
            $envContent = preg_replace('/^TURNSTILE_KEY=.*$/m', '', $envContent);
            $newLine = sprintf("TURNSTILE_KEY=%s\n", $url);
        } elseif ($type === SettingsConfigType::TURNSTILE_SECRET->value) {
            $envContent = preg_replace('/^TURNSTILE_SECRET=.*$/m', '', $envContent);
            $newLine = sprintf("TURNSTILE_SECRET=%s\n", $url);
        } elseif ($type === SettingsConfigType::JWT_SECRET_KEY->value) {
            $envContent = preg_replace('/^JWT_SECRET_KEY=.*$/m', '', $envContent);
            $newLine = sprintf("JWT_SECRET_KEY=%s\n", $url);
        } elseif ($type === SettingsConfigType::JWT_PUBLIC_KEY->value) {
            $envContent = preg_replace('/^JWT_PUBLIC_KEY=.*$/m', '', $envContent);
            $newLine = sprintf("JWT_PUBLIC_KEY=%s\n", $url);
        } elseif ($type === SettingsConfigType::JWT_PASSPHRASE->value) {
            $envContent = preg_replace('/^JWT_PASSPHRASE=.*$/m', '', $envContent);
            $newLine = sprintf("JWT_PASSPHRASE=%s\n", $url);
        } else {
            $newLine = '';
        }

        file_put_contents($envPath, trim($envContent) . "\n" . $newLine);
    }

    private function getDriverFromScheme(string $scheme): string
    {
        return match ($scheme) {
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
            default => throw new \InvalidArgumentException("Unsupported database scheme: $scheme"),
        };
    }

    public function buildDatabaseUrl(
        string $username,
        string $password,
        string $host,
        int $port,
        string $database,
        string $serverVersion = '8',
        string $charset = 'utf8mb4'
    ): string {
        return sprintf(
            'mysql://%s:%s@%s:%d/%s?serverVersion=%s&charset=%s',
            urlencode($username),
            urlencode($password),
            $host,
            $port,
            $database,
            $serverVersion,
            $charset
        );
    }

    public function parseDatabaseUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false) {
            throw new InvalidArgumentException('Invalid database URL');
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        return [
            'username' => isset($parts['user']) ? urldecode($parts['user']) : null,
            'password' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
            'host' => $parts['host'] ?? null,
            'port' => isset($parts['port']) ? (int)$parts['port'] : null,
            'database' => isset($parts['path']) ? ltrim($parts['path'], '/') : null,
            'serverVersion' => $query['serverVersion'] ?? null,
            'charset' => $query['charset'] ?? null,
        ];
    }
}
