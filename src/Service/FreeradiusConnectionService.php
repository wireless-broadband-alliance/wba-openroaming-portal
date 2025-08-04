<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Throwable;

readonly class FreeradiusConnectionService
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
        } catch (Throwable) {
            return [
                'success' => false,
                'message' => 'FreeRADIUS DB connection failed: Failed to connect to the database. ' .
                    'Please check your connection details on the .env configuration.',
            ];
        }
    }
}
