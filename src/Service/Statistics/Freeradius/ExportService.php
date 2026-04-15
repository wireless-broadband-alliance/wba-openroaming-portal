<?php

namespace App\Service\Statistics\Freeradius;

use App\Service\Statistics\DashboardFormatter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

readonly class ExportService
{
    public function __construct(
        private DashboardFormatter $formatter
    ) {
    }

    /**
     * @throws Exception
     */
    public function export(array $data): string
    {
        $spreadsheet = new Spreadsheet();

        $this->buildAuthSheet($spreadsheet, $data['auth'] ?? []);
        $this->buildSessionAvgSheet($spreadsheet, $data['sessionAvg'] ?? []);
        $this->buildSessionTotalSheet($spreadsheet, $data['sessionTotal'] ?? []);
        $this->buildTrafficSheet($spreadsheet, $data['traffic'] ?? []);
        $this->buildRealmSheet($spreadsheet, $data['realms'] ?? []);
        $this->buildApSheet($spreadsheet, $data['apUsage'] ?? []);
        $this->buildWifiSheet($spreadsheet, $data['wifi'] ?? []);

        $file = tempnam(sys_get_temp_dir(), 'freeradius_export') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        return $file;
    }

    private function buildAuthSheet(Spreadsheet $spreadsheet, array $auth): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Authentications');

        $sheet->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Accepted')
            ->setCellValue('C1', 'Rejected');

        $row = 2;

        foreach ($auth as $date => $values) {
            $sheet->setCellValue('A' . $row, $date)
                ->setCellValue('B' . $row, $values['accepted'] ?? 0)
                ->setCellValue('C' . $row, $values['rejected'] ?? 0);

            $row++;
        }
    }

    private function buildSessionAvgSheet(Spreadsheet $spreadsheet, array $sessionAvg): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Session Average');

        $sheet->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Average Session Time');

        $row = 2;

        foreach ($sessionAvg as $date => $value) {
            $sheet->setCellValue('A' . $row, $date)
                ->setCellValue('B' . $row, $this->formatter->formatSeconds((int)$value));

            $row++;
        }
    }

    private function buildSessionTotalSheet(Spreadsheet $spreadsheet, array $sessionTotal): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Session Total');

        $sheet->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Total Session Time');

        $row = 2;

        foreach ($sessionTotal as $date => $value) {
            $sheet->setCellValue('A' . $row, $date)
                ->setCellValue('B' . $row, $this->formatter->formatSeconds((int)$value));

            $row++;
        }
    }

    private function buildTrafficSheet(Spreadsheet $spreadsheet, array $traffic): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Traffic');

        $sheet->setCellValue('A1', 'Realm')
            ->setCellValue('B1', 'Input')
            ->setCellValue('C1', 'Output');

        $row = 2;

        foreach ($traffic as $realm => $values) {
            $sheet->setCellValue('A' . $row, $realm)
                ->setCellValue('B' . $row, $this->formatter->formatBytes((int)($values['input'] ?? 0)))
                ->setCellValue('C' . $row, $this->formatter->formatBytes((int)($values['output'] ?? 0)));

            $row++;
        }
    }

    private function buildApSheet(Spreadsheet $spreadsheet, array $apUsage): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Access Points Usage');

        $sheet->setCellValue('A1', 'AP')
            ->setCellValue('B1', 'Usage');

        $row = 2;

        foreach ($apUsage as $ap => $count) {
            $sheet->setCellValue('A' . $row, $ap)
                ->setCellValue('B' . $row, $count);

            $row++;
        }
    }

    private function buildRealmSheet(Spreadsheet $spreadsheet, array $realms): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Realm Usage');

        $sheet->setCellValue('A1', 'Realm')
            ->setCellValue('B1', 'Usage');

        $row = 2;

        foreach ($realms as $realm => $count) {
            $sheet->setCellValue('A' . $row, $realm)
                ->setCellValue('B' . $row, $count);

            $row++;
        }
    }

    private function buildWifiSheet(Spreadsheet $spreadsheet, array $wifi): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('WiFi');

        $sheet->setCellValue('A1', 'Standard')
            ->setCellValue('B1', 'Usage');

        $row = 2;

        foreach ($wifi as $standard => $count) {
            $sheet->setCellValue('A' . $row, $standard)
                ->setCellValue('B' . $row, $count);

            $row++;
        }
    }
}
