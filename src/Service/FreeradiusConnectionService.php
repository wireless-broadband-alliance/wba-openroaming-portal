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
}
