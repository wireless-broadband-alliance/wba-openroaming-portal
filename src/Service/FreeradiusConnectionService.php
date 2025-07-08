<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Throwable;

class FreeradiusConnectionService
{
    private Connection $freeradiusConnection;

    public function __construct(ManagerRegistry $doctrine)
    {
        $connection = $doctrine->getConnection('freeradius');

        if (!$connection instanceof Connection) {
            throw new RuntimeException('Invalid connection type.');
        }

        $this->freeradiusConnection = $connection;
    }

    public function checkConnection(): array
    {
        try {
            $this->freeradiusConnection->executeQuery('SELECT 1');
            return [
                'success' => true,
                'message' => 'FreeRADIUS DB connection - Successfully connected.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'FreeRADIUS DB connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
