<?php

namespace App\Service;

use App\Enum\DataBaseSetupType;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DatabaseConnectionService
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
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
                'dbname'   => $dbname,
                'user'     => $parts['user'],
                'password' => $parts['pass'] ?? null,
                'host'     => $parts['host'],
                'port'     => $parts['port'] ?? null,
                'driver'   => $this->getDriverFromScheme($parts['scheme']),
            ];

            $connection = DriverManager::getConnection($connectionParams);
            $connection->executeQuery('SELECT 1');

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    public function writeDatabaseUrlToEnv(string $url, string $type): void
    {
        $envPath = $this->params->get('kernel.project_dir').'/.env';
        $envContent = file_get_contents($envPath);

        if ($type === DataBaseSetupType::DATABASE_URL->value) {
            $envContent = preg_replace('/^DATABASE_URL=.*$/m', '', $envContent);
            $newLine = sprintf("DATABASE_URL=\"%s\"\n", $url);
        } elseif ($type === DataBaseSetupType::DATABASE_FREERADIUS_URL->value) {
            $envContent = preg_replace('/^DATABASE_FREERADIUS_URL=.*$/m', '', $envContent);
            $newLine = sprintf("DATABASE_FREERADIUS_URL=\"%s\"\n", $url);
        }

        file_put_contents($envPath, trim($envContent) . "\n" . $newLine);
    }

    private function getDriverFromScheme(string $scheme): string
    {
        return match($scheme) {
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
            default => throw new \InvalidArgumentException("Unsupported database scheme: $scheme"),
        };
    }
}