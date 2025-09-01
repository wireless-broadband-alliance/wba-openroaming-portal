<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Throwable;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class FreeradiusConnectionService
{
    private Connection $freeradiusConnection;

    public function __construct(
        ManagerRegistry $doctrine,
        private TranslatorInterface $translator
    ) {
        $connection = $doctrine->getConnection('freeradius');

        if (!$connection instanceof Connection) {
            throw new RuntimeException($this->translator->trans(
                'invalidConnectionType',
                [],
                'FreeradiusConnectionService'
            ));
        }

        $this->freeradiusConnection = $connection;
    }

    public function checkConnection(): array
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
                'message' => $this->translator->trans('FreeRADIUSConnectionFailed', [], 'FreeradiusConnectionService'),
            ];
        }
    }
}
