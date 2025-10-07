<?php

namespace App\Service;

use App\Enum\DataBaseSetupType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DatabaseConnectionService
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }
    public function testDatabaseConnection(string $url): array
    {
        try {
            $parts = parse_url($url);
            if (!$parts || !isset($parts['scheme'])) {
                return [
                    'success' => false,
                    'error' => 'Formato de URL inválido.',
                ];
            }

            $driverMap = [
                'mysql' => 'pdo_mysql',
                'pgsql' => 'pdo_pgsql',
                'postgres' => 'pdo_pgsql',
                'sqlite' => 'pdo_sqlite',
            ];
            $driver = $driverMap[$parts['scheme']] ?? null;

            if (!$driver) {
                return [
                    'success' => false,
                    'error' => sprintf('Driver não suportado: %s', $parts['scheme']),
                ];
            }

            if (($parts['host'] ?? '') === 'localhost') {
                $parts['host'] = '127.0.0.1';
                $url = $this->rebuildUrl($parts);
            }

            $connection = DriverManager::getConnection([
                'url' => $url,
                'driver' => $driver,
            ]);

            $connection->getNativeConnection();

            return [
                'success' => true,
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url,
            ];
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
}