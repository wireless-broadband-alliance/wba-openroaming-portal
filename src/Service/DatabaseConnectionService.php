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

    public function writeDatabaseUrlToEnv(string $url, string $type): bool
    {
        $envPath = $this->params->get('kernel.project_dir') . '/.env';

        if (!is_writable($envPath)) {
            return false;
        }

        $envContent = file_get_contents($envPath);

        if ($envContent === false) {
            return false; // Ensures it's always a string for preg_replace/rtrim
        }

        $newLine = '';
        $regex = '';

        if ($type === DataBaseSetupType::DATABASE_URL->value) {
            $regex = '/^DATABASE_URL=.*$/m';
            $newLine = sprintf('DATABASE_URL="%s"', $url);
        } elseif ($type === DataBaseSetupType::DATABASE_FREERADIUS_URL->value) {
            $regex = '/^DATABASE_FREERADIUS_URL=.*$/m';
            $newLine = sprintf('DATABASE_FREERADIUS_URL="%s"', $url);
        } elseif ($type === SettingsConfigType::TRUSTED_PROXIES->value) {
            $regex = '/^TRUSTED_PROXIES=.*$/m';
            $newLine = sprintf('TRUSTED_PROXIES=%s', $url);
        } elseif ($type === SettingsConfigType::TURNSTILE_KEY->value) {
            $regex = '/^TURNSTILE_KEY=.*$/m';
            $newLine = sprintf('TURNSTILE_KEY=%s', $url);
        } elseif ($type === SettingsConfigType::TURNSTILE_SECRET->value) {
            $regex = '/^TURNSTILE_SECRET=.*$/m';
            $newLine = sprintf('TURNSTILE_SECRET=%s', $url);
        } elseif ($type === SettingsConfigType::JWT_SECRET_KEY->value) {
            $regex = '/^JWT_SECRET_KEY=.*$/m';
            $newLine = sprintf('JWT_SECRET_KEY=%s', $url);
        } elseif ($type === SettingsConfigType::JWT_PUBLIC_KEY->value) {
            $regex = '/^JWT_PUBLIC_KEY=.*$/m';
            $newLine = sprintf('JWT_PUBLIC_KEY=%s', $url);
        } elseif ($type === SettingsConfigType::JWT_PASSPHRASE->value) {
            $regex = '/^JWT_PASSPHRASE=.*$/m';
            $newLine = sprintf('JWT_PASSPHRASE=%s', $url);
        }

        if (!$regex) {
            throw new InvalidArgumentException("Invalid environment type: $type");
        }

        $updated = preg_replace($regex, $newLine, $envContent, -1, $count);

        // preg_replace can return null if something goes wrong, cast to string
        if ($updated === null) {
            return false;
        }

        if ($count === 0) {
            $updated = rtrim($envContent) . "\n" . $newLine . "\n";
        } else {
            $updated = rtrim((string)$updated) . "\n";
        }

        return file_put_contents($envPath, $updated) !== false;
    }

    /**
     * @param string $scheme
     * @return 'pdo_mysql'|'pdo_pgsql'|'pdo_sqlite'
     *
     * @throws InvalidArgumentException
     */
    private function getDriverFromScheme(string $scheme): string
    {
        return match ($scheme) {
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
            default => throw new InvalidArgumentException("Unsupported database scheme: $scheme"),
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

    /**
     * @param string $url
     * @return array{
     *   username: string|null,
     *   password: string|null,
     *   host: string|null,
     *   port: int|null,
     *   database: string|null,
     *   serverVersion: string|null,
     *   charset: string|null
     * }
     *
     * @throws InvalidArgumentException
     */
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

        $serverVersion = $query['serverVersion'] ?? null;
        if (is_array($serverVersion)) {
            $serverVersion = (string) reset($serverVersion);
        }

        $charset = $query['charset'] ?? null;
        if (is_array($charset)) {
            $charset = (string) reset($charset);
        }

        return [
            'username' => isset($parts['user']) ? urldecode($parts['user']) : null,
            'password' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
            'host' => $parts['host'] ?? null,
            'port' => isset($parts['port']) ? (int) $parts['port'] : null,
            'database' => isset($parts['path']) ? ltrim($parts['path'], '/') : null,
            'serverVersion' => $serverVersion !== null ? (string) $serverVersion : null,
            'charset' => $charset !== null ? (string) $charset : null,
        ];
    }
}
