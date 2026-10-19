<?php

declare(strict_types=1);

namespace App\Controller;

use App\Csv\CsvFormulaSanitizerTrait;
use App\DTO\AccessPointDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Enum\AdminPermissionsType;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class MapAccessPointExportController extends AbstractController
{
    use CsvFormulaSanitizerTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Export only this network's access points.
     * @throws MappingException
     */
    #[Route(
        'dashboard/map/network/{id:network<\d+>}/accessPoints/export',
        name: 'admin_dashboard_map_network_accessPoints_export'
    )]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function exportToCsv(Network $network, Connection $connection, EntityManagerInterface $em): StreamedResponse
    {
        $meta = $em->getClassMetadata(AccessPoint::class);
        $table = $meta->getTableName();
        $col = static fn(string $field): string => $meta->getColumnName($field);
        $networkFkColumn = $meta->getSingleAssociationJoinColumnName('network');

        $sql = strtr(
            <<<'SQL'
                SELECT
                    ap.__name__ AS name,
                    ap.__ssid__ AS ssid,
                    ap.__mac__ AS mac_address,
                    ap.__vendor__ AS vendor,
                    ap.__model__ AS model,
                    ap.__standard__ AS standard,
                    ap.__serial__ AS serial_number,
                    ST_X(ap.__location__) AS longitude,
                    ST_Y(ap.__location__) AS latitude,
                    ap.__altMsl__ AS altitude_msl,
                    ap.__altAgl__ AS altitude_agl
                FROM __table__ ap
                WHERE ap.__networkFk__ = :networkId
                ORDER BY ap.id ASC
            SQL,
            [
                '__name__' => $col('name'),
                '__ssid__' => $col('ssid'),
                '__mac__' => $col('macAddress'),
                '__vendor__' => $col('vendor'),
                '__model__' => $col('model'),
                '__standard__' => $col('standard'),
                '__serial__' => $col('serialNumber'),
                '__location__' => $col('location'),
                '__altMsl__' => $col('altitudeMsl'),
                '__altAgl__' => $col('altitudeAgl'),
                '__table__' => $table,
                '__networkFk__' => $networkFkColumn,
            ]
        );

        $response = new StreamedResponse(function () use ($network, $connection, $sql): void {
            $handle = fopen('php://output', 'wb+');

            if ($handle === false) {
                throw new RuntimeException('Could not open the output stream for the CSV.');
            }

            fwrite($handle, "\xEF\xBB\xBF");

            try {
                fputcsv(
                    $handle,
                    [
                        'ap_name',
                        'ap_ssid',
                        'ap_mac_address',
                        'ap_vendor',
                        'ap_model',
                        'ap_standard',
                        'ap_serial_number',
                        'ap_longitude',
                        'ap_latitude',
                        'ap_altitude_msl',
                        'ap_altitude_agl',
                    ],
                    escape: '\\'
                );

                $result = $connection->executeQuery($sql, ['networkId' => $network->getId()]);

                foreach ($result->iterateAssociative() as $row) {
                    $lng = $row['longitude'];
                    $lat = $row['latitude'];

                    // Same "0,0 means no coordinates" convention as before.
                    if ($lng === null || (float)$lng === 0.0) {
                        $lng = '';
                        $lat = '';
                    }

                    fputcsv(
                        $handle,
                        [
                            $this->sanitizeCsvField($row['name']),
                            $this->sanitizeCsvField($row['ssid']),
                            $this->sanitizeCsvField($row['mac_address']),
                            $this->sanitizeCsvField($row['vendor']),
                            $this->sanitizeCsvField($row['model']),
                            $this->sanitizeCsvField($row['standard']),
                            $this->sanitizeCsvField($row['serial_number']),
                            $lng !== '' ? number_format(
                                (float)$lng,
                                7,
                                '.',
                                ''
                            ) : '',
                            $lat !== '' ? number_format(
                                (float)$lat,
                                7,
                                '.',
                                ''
                            ) : '',
                            $row['altitude_msl'],
                            $row['altitude_agl'],
                        ],
                        escape: '\\'
                    );
                }
            } catch (Throwable $e) {
                fputcsv(
                    $handle,
                    [
                        'FATAL ERROR:',
                        $e->getMessage(),
                        'LINE: ' . $e->getLine(),
                        'FILE: ' . $e->getFile(),
                    ],
                    escape: '\\'
                );
            }

            fclose($handle);
        });

        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $network->getName() ?? 'network');

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="export_%s_access_points.csv"', $safeName)
        );

        return $response;
    }

    /**
     * Import access points into this network only. Does not create/touch other networks.
     */
    #[Route(
        'dashboard/map/network/{id:network<\d+>}/accessPoints/import',
        name: 'admin_dashboard_map_network_accessPoints_import'
    )]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function importCsv(
        Network $network,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('import_file');

        if (!$file) {
            $this->addFlash('error', $this->translator->trans('importErrorNoFile', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
        }

        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/x-csv',
            'application/vnd.ms-excel',
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            $this->addFlash('error', $this->translator->trans('importErrorInvalidMimeType', [], 'controllers'));
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                ['id' => $network->getId()]
            );
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || !is_readable($realPath)) {
            $this->addFlash('error', $this->translator->trans('importErrorNotReadable', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
        }

        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            $this->addFlash('error', $this->translator->trans('importErrorCannotOpen', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, ',', escape: '\\');
        if (!$headers || !in_array('ap_name', $headers, true)) {
            fclose($handle);
            $this->addFlash('error', $this->translator->trans('importErrorInvalidStructure', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
        }

        $cols = array_flip($headers);
        $idxName   = $cols['ap_name'] ?? 0;
        $idxSsid   = $cols['ap_ssid'] ?? 1;
        $idxMac    = $cols['ap_mac_address'] ?? 2;
        $idxVendor = $cols['ap_vendor'] ?? 3;
        $idxModel  = $cols['ap_model'] ?? 4;
        $idxStd    = $cols['ap_standard'] ?? 5;
        $idxSerial = $cols['ap_serial_number'] ?? 6;
        $idxLng    = $cols['ap_longitude'] ?? 7;
        $idxLat    = $cols['ap_latitude'] ?? 8;
        $idxMsl    = $cols['ap_altitude_msl'] ?? 9;
        $idxAgl    = $cols['ap_altitude_agl'] ?? 10;

        $apsImportedCount = 0;
        $apsUpdatedCount = 0;
        $rowErrors = [];
        $validRows = [];
        $rowNumber = 1;

        try {
            while (($row = fgetcsv($handle, 0, ',', escape: '\\')) !== false) {
                $rowNumber++;

                $apName = $this->sanitizeCsvField($row[$idxName] ?? '');
                if (in_array($apName, ['', '0', "'0"], true)) {
                    continue;
                }

                $apSsid   = $this->sanitizeCsvField($row[$idxSsid] ?? '');
                $apMac    = trim($row[$idxMac] ?? '');
                $apVendor = $this->sanitizeCsvField($row[$idxVendor] ?? '');
                $apModel  = $this->sanitizeCsvField($row[$idxModel] ?? '');
                $apStd    = $this->sanitizeCsvField($row[$idxStd] ?? '');
                $apSerial = trim($row[$idxSerial] ?? '');

                $rawLng = str_replace(',', '.', ltrim(trim($row[$idxLng] ?? ''), "'"));
                $rawLat = str_replace(',', '.', ltrim(trim($row[$idxLat] ?? ''), "'"));
                $rawMsl = str_replace(',', '.', ltrim(trim($row[$idxMsl] ?? ''), "'"));
                $rawAgl = str_replace(',', '.', ltrim(trim($row[$idxAgl] ?? ''), "'"));

                // Build the DTO exactly like the create/edit form would.
                $dto = new AccessPointDTO();
                $dto->network      = $network;
                $dto->name         = $apName;
                $dto->ssid         = ($apSsid === '' || $apSsid === '0') ? 'OpenRoaming' : $apSsid;
                $dto->macAddress   = ($apMac === '' || $apMac === '0') ? null : $apMac;
                $dto->vendor       = ($apVendor === '' || $apVendor === '0') ? null : $apVendor;
                $dto->model        = ($apModel === '' || $apModel === '0') ? null : $apModel;
                $dto->standard     = ($apStd === '' || $apStd === '0') ? null : $apStd;
                $dto->serialNumber = ($apSerial === '' || $apSerial === '0') ? null : $apSerial;

                $dto->longitude   = $rawLat !== '' ? $rawLat : null;
                $dto->latitude    = $rawLng !== '' ? $rawLng : null;

                $dto->altitudeMsl = $rawMsl !== '' ? (float) $rawMsl : null;
                $dto->altitudeAgl = $rawAgl !== '' ? (float) $rawAgl : null;

                $violations = $this->validator->validate($dto);

                if (count($violations) > 0) {
                    foreach ($violations as $violation) {
                        $rowErrors[] = [
                            'row'     => $rowNumber,
                            'name'    => $apName,
                            'field'   => $violation->getPropertyPath() ?: 'general',
                            'message' => $violation->getMessage(),
                        ];
                    }
                    continue;
                }

                $validRows[] = ['dto' => $dto, 'mac' => $apMac, 'name' => $apName];
            }

            // Atomic: any error at all → abort, write nothing.
            if ($rowErrors !== []) {
                $groupedErrors = [];
                foreach ($rowErrors as $err) {
                    $key = $err['field'] . '|' . $err['message'];
                    $groupedErrors[$key] ??= [
                        'field'   => $err['field'],
                        'message' => $err['message'],
                        'rows'    => [],
                    ];
                    $groupedErrors[$key]['rows'][] = [
                        'row'  => $err['row'],
                        'name' => $err['name'],
                    ];
                }

                usort(
                    $groupedErrors,
                    static fn(array $a, array $b): int => count($b['rows']) <=> count($a['rows'])
                );

                $this->addFlash('import_errors', $groupedErrors);
                // no separate 'import_errors_total' flash — the template derives it
                $this->addFlash('error', $this->translator->trans('importErrorValidation', [], 'controllers'));
                return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
            }

            // Everything validated — now actually write it, inside one transaction.
            $em->wrapInTransaction(
                function () use ($em, $network, $validRows, &$apsImportedCount, &$apsUpdatedCount): void {
                    $now = new DateTimeImmutable();

                    foreach ($validRows as $entry) {
                        $dto = $entry['dto'];
                        $apMac = $entry['mac'];
                        $apName = $entry['name'];

                        $existingAp = null;
                        foreach ($network->getAccessPoints() as $currentAp) {
                            if ($apMac !== '' && $apMac !== '0' && $currentAp->getMacAddress() === $apMac) {
                                $existingAp = $currentAp;
                                break;
                            }
                            if (($apMac === '' || $apMac === '0') && $currentAp->getName() === $apName) {
                                $existingAp = $currentAp;
                                break;
                            }
                        }

                        if ($existingAp) {
                            $ap = $existingAp;
                            $apsUpdatedCount++;
                        } else {
                            $ap = new AccessPoint();
                            $ap->setCreatedAt($now);
                            $network->addAccessPoint($ap);
                            $apsImportedCount++;
                        }

                        $dto->updateEntity($ap);
                        $em->persist($ap);
                    }

                    $em->persist($network);
                }
            );

            $this->addFlash(
                'success',
                sprintf(
                    $this->translator->trans('accessPointImportSuccess', [], 'controllers'),
                    $apsImportedCount,
                    $apsUpdatedCount
                )
            );
        } catch (Throwable $e) {
            $this->addFlash(
                'error',
                sprintf(
                    $this->translator->trans('importErrorGeneral', [], 'controllers'),
                    $e->getMessage()
                )
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
    }
}
