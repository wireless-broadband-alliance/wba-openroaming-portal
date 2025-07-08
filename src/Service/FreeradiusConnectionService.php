<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Throwable;

readonly class FreeradiusConnectionService
{
    public function __construct(
        private Connection $freeradiusConnection
    ) {
    }

    /**
     * Check if the freeradius connection is alive.
     */
    public function checkConnection(): array
    {
        try {
            // Explicitly try to connect
            $this->freeradiusConnection->connect();

            if ($this->freeradiusConnection->isConnected()) {
                return [
                    'success' => true,
                    'message' => 'FreeRADIUS DB connection - Successfully connected.',
                ];
            }

            return [
                'success' => false,
                'message' => 'FreeRADIUS DB connection - Failed to connect (unknown reason).',
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'FreeRADIUS DB connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
