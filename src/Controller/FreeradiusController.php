<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EscapeSpreadSheet;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\Statistics;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FreeradiusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EventActions $eventActions,
        private readonly RadiusAuthsRepository $radiusAuthsRepository,
        private readonly RadiusAccountingRepository $radiusAccountingRepository
    ) {
    }

    /**
     * Render Statistics about the freeradius data
     */
    /**
     * @throws \JsonException
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    #[Route('/dashboard/statistics/freeradius', name: 'admin_dashboard_statistics_freeradius')]
    #[IsGranted('ROLE_ADMIN')]
    public function freeradiusStatisticsData(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] ?int $count = 5
    ): Response {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $user = $this->getUser();
        $export_freeradius_statistics = $this->parameterBag->get('app.export_freeradius_statistics');

        // Get the submitted start and end dates from the form
        $startDateString = $request->query->get('startDate');
        $endDateString = $request->query->get('endDate');

        // Convert the date strings to DateTime objects
        $startDate = $startDateString ? new DateTime($startDateString) : new DateTime()->modify(
            '-1 week'
        );

        $endDate = $endDateString ? new DateTime($endDateString) : new DateTime();

        $interval = $startDate->diff($endDate);
        if ($interval->days > 365) {
            $this->addFlash(
                'error_admin',
                'Maximum date range is 1 year'
            );

            return $this->redirectToRoute('admin_dashboard_statistics_freeradius');
        }

        // Fetch all the required data, graphics, etc...
        $statisticsService = new Statistics(
            $this->entityManager,
            $this->radiusAuthsRepository,
            $this->radiusAccountingRepository
        );
        $fetchChartAuthenticationsFreeradius = $statisticsService
            ->fetchChartAuthenticationsFreeradius($startDate, $endDate);
        $fetchChartRealmsFreeradius = $statisticsService->fetchChartRealmsFreeradius($startDate, $endDate);
        $fetchChartCurrentAuthFreeradius = $statisticsService->fetchChartCurrentAuthFreeradius();
        $fetchChartTrafficFreeradius = $statisticsService->fetchChartTrafficFreeradius($startDate, $endDate);
        $fetchChartSessionAverageFreeradius = $statisticsService
            ->fetchChartSessionAverageFreeradius($startDate, $endDate);
        $fetchChartSessionTotalFreeradius = $statisticsService->fetchChartSessionTotalFreeradius($startDate, $endDate);
        $fetchChartWifiTags = $statisticsService->fetchChartWifiVersion($startDate, $endDate);
        $fetchChartApUsage = $statisticsService->fetchChartApUsage($startDate, $endDate);

        $memory_before = memory_get_usage();
        $memory_after = memory_get_usage();
        $memory_diff = $memory_after - $memory_before;

        // Check that the memory usage does not exceed the PHP memory limit of 128M
        if ($memory_diff > 134217728) {
            $this->addFlash(
                'error_admin',
                'Maximum date range is 1 year'
            );

            return $this->redirectToRoute('admin_dashboard_statistics_freeradius');
        }

        // Extract the connection attempts
        $authCounts = [
            'Accepted' => array_sum($fetchChartAuthenticationsFreeradius['datasets'][0]['data']),
            'Rejected' => array_sum($fetchChartAuthenticationsFreeradius['datasets'][1]['data']),
        ];

        // Extract all realms names and usage
        $realmsUsage = [];
        foreach ($fetchChartRealmsFreeradius as $content) {
            $realm = $content['realm'];
            $usage = $content['count'];

            if (isset($realmsUsage[$realm])) {
                $realmsUsage[$realm] += $usage;
            } else {
                $realmsUsage[$realm] = $usage;
            }
        }

        // Sum all the current authentication
        $totalCurrentAuths = 0;
        foreach ($fetchChartCurrentAuthFreeradius['datasets'] as $dataset) {
            // Sum the data points in the current dataset
            $totalCurrentAuths = array_sum($dataset['data']) + $totalCurrentAuths;
        }

        // Sum all the traffic from the Accounting table
        $totalTraffic = [
            'total_input' => 0,
            'total_output' => 0,
        ];
        foreach ($fetchChartTrafficFreeradius as $content) {
            $totalTraffic['total_input'] += $content['total_input'];
            $totalTraffic['total_output'] += $content['total_output'];
        }
        $totalTraffic['total_input'] = number_format($totalTraffic['total_input'] / (1024 * 1024 * 1024), 1);
        $totalTraffic['total_output'] = number_format($totalTraffic['total_output'] / (1024 * 1024 * 1024), 1);

        // Extract the average time
        $averageTimes = $fetchChartSessionAverageFreeradius['datasets'][0]['data'];
        $totalAverageTimeSeconds = array_sum($averageTimes);

        // Convert the total average time to human-readable format
        $totalAverageTimeReadable = sprintf(
            '%dh %dm',
            floor($totalAverageTimeSeconds / 3600),
            floor(($totalAverageTimeSeconds % 3600) / 60)
        );

        // Extract the total time
        $totalTimes = $fetchChartSessionTotalFreeradius['datasets'][0]['data'];
        $totalTimeSeconds = array_sum($totalTimes);

        // Convert the total time to human-readable format
        $totalTimeReadable = sprintf(
            '%dh %dm',
            floor($totalTimeSeconds / 3600),
            floor(($totalTimeSeconds % 3600) / 60)
        );

        $totalApCount = count($fetchChartApUsage);
        $totalPages = ceil($totalApCount / $count);
        $offset = ($page - 1) * $count;
        $fetchChartApUsage = array_slice($fetchChartApUsage, $offset, $count);

        return $this->render('admin/freeradius_statistics.html.twig', [
            'user' => $user,
            'data' => $data,
            'current_user' => $user,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'count' => $count,
            'totalApCount' => $totalApCount,
            'realmsUsage' => $realmsUsage,
            'authCounts' => $authCounts,
            'totalCurrentAuths' => $totalCurrentAuths,
            'totalTrafficFreeradius' => $totalTraffic,
            'sessionTimeAverage' => $totalAverageTimeReadable,
            'totalTime' => $totalTimeReadable,
            'authAttemptsJson' => json_encode(
                $fetchChartAuthenticationsFreeradius,
                JSON_THROW_ON_ERROR
            ),
            'sessionTimeJson' => json_encode(
                $fetchChartSessionAverageFreeradius,
                JSON_THROW_ON_ERROR
            ),
            'totalTimeJson' => json_encode($fetchChartSessionTotalFreeradius, JSON_THROW_ON_ERROR),
            'wifiTagsJson' => json_encode($fetchChartWifiTags, JSON_THROW_ON_ERROR),
            'ApUsage' => $fetchChartApUsage,
            'selectedStartDate' => $startDate->format('Y-m-d\TH:i'),
            'selectedEndDate' => $endDate->format('Y-m-d\TH:i'),
            'exportFreeradiusStatistics' => $export_freeradius_statistics,
            'paginationApUsage' => true,
        ]);
    }

    /**
     * Exports the freeradius data
     * @throws Exception
     */
    #[Route('/dashboard/export/freeradius', name: 'admin_page_export_freeradius')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportFreeradius(Request $request): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Get the submitted start and end dates from the form
        $startDateString = $request->query->get('startDate');
        $endDateString = $request->query->get('endDate');

        // Convert the date strings to DateTime objects
        $startDate = $startDateString ? new DateTime($startDateString) : new DateTime()->modify('-1 week');
        $endDate = $endDateString ? new DateTime($endDateString) : new DateTime();

        // Fetch the authentication data
        $statisticsService = new Statistics(
            $this->entityManager,
            $this->radiusAuthsRepository,
            $this->radiusAccountingRepository
        );
        $fetchChartAuthenticationsFreeradius = $statisticsService
            ->fetchChartAuthenticationsFreeradius($startDate, $endDate);
        $fetchChartSessionAverageFreeradius = $statisticsService
            ->fetchChartSessionAverageFreeradius($startDate, $endDate);
        $fetchChartSessionTotalFreeradius = $statisticsService->fetchChartSessionTotalFreeradius($startDate, $endDate);
        $fetchChartTrafficFreeradius = $statisticsService->fetchChartTrafficFreeradius($startDate, $endDate);
        $fetchChartRealmsFreeradius = $statisticsService->fetchChartRealmsFreeradius($startDate, $endDate);
        $fetchChartApUsage = $statisticsService->fetchChartApUsage($startDate, $endDate);
        $fetchChartWifiTags = $statisticsService->fetchChartWifiVersion($startDate, $endDate);

        // Prepare the authentication data for Excel
        $authData = [];
        foreach ($fetchChartAuthenticationsFreeradius['labels'] as $index => $auth_date) {
            $accepted = $fetchChartAuthenticationsFreeradius['datasets'][0]['data'][$index] ?? 0;
            $rejected = $fetchChartAuthenticationsFreeradius['datasets'][1]['data'][$index] ?? 0;

            $authData[] = [
                'auth_date' => $auth_date,
                'Accepted' => $accepted,
                'Rejected' => $rejected,
            ];
        }

        // Prepare the session average data for Excel
        $sessionData = [];
        foreach ($fetchChartSessionAverageFreeradius['labels'] as $index => $session_date) {
            $sessionAverage = $fetchChartSessionAverageFreeradius['datasets'][0]['tooltips'][$index] ?? 0;
            $sessionData[] = [
                'session_date' => $session_date,
                'average_time' => $sessionAverage,
            ];
        }

        // Prepare the session total data for Excel
        $totalTimeData = [];
        foreach ($fetchChartSessionTotalFreeradius['labels'] as $index => $session_date) {
            $sessionTotal = $fetchChartSessionTotalFreeradius['datasets'][0]['tooltips'][$index] ?? 0;
            $totalTimeData[] = [
                'session_date' => $session_date,
                'total_time' => $sessionTotal,
            ];
        }

        // Prepare the total traffic data for Excel
        $trafficData = [];
        foreach ($fetchChartTrafficFreeradius as $session_date) {
            $realm = $fetchChartTrafficFreeradius[0]['realm'] ?? 0;
            $totalInput = $fetchChartTrafficFreeradius[0]['total_input'] ?? 0;
            $totalOutput = $fetchChartTrafficFreeradius[0]['total_output'] ?? 0;

            $trafficData[] = [
                'realm' => $realm,
                'total_input_flat' => $totalInput,
                'total_input' => number_format($totalInput / (1024 * 1024 * 1024), 1),
                'total_output_flat' => $totalOutput,
                'total_output' => number_format($totalOutput / (1024 * 1024 * 1024), 1),
            ];
        }

        // Prepare the realm Usage data for Excel
        $realmUsageData = [];
        foreach ($fetchChartRealmsFreeradius as $session_date) {
            $realm = $fetchChartRealmsFreeradius[0]['realm'] ?? 0;
            $totalCount = $fetchChartRealmsFreeradius[0]['count'] ?? 0;

            $realmUsageData[] = [
                'realm' => $realm,
                'total_count' => $totalCount,
            ];
        }

        // Prepare the realm Usage data for Excel
        $realmUsageData = [];
        foreach ($fetchChartRealmsFreeradius as $session_date) {
            $realm = $fetchChartRealmsFreeradius[0]['realm'] ?? 0;
            $totalCount = $fetchChartRealmsFreeradius[0]['count'] ?? 0;

            $realmUsageData[] = [
                'realm' => $realm,
                'total_count' => $totalCount,
            ];
        }

        // Prepare the AP Usage data for Excel
        $apUsageData = [];
        foreach (array_keys($fetchChartApUsage) as $index) {
            $apName = $fetchChartApUsage[$index]['ap'] ?? 0;
            $apUsage = $fetchChartApUsage[$index]['count'] ?? 0;
            $apUsageData[] = [
                'ap_Name' => $apName,
                'ap_Usage' => $apUsage,
            ];
        }

        // Prepare the Wi-Fi Standards Usage data for Excel
        $wifiStandardsData = [];
        foreach ($fetchChartWifiTags['labels'] as $index => $wifi_Standards) {
            $wifiUsage = $fetchChartWifiTags['datasets'][0]['data'][$index] ?? 0;
            $wifiStandardsData[] = [
                'wifi_Standards' => $wifi_Standards,
                'wifi_Usage' => $wifiUsage,
            ];
        }

        // Create a new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        // Fill the first sheet with authentication data
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Authentications');
        $sheet1->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Accepted')
            ->setCellValue('C1', 'Rejected');

        $row = 2;

        $escapeSpreadSheetService = new EscapeSpreadSheet();

        foreach ($authData as $data) {
            $sheet1->setCellValue('A' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['auth_date']))
                ->setCellValue('B' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['Accepted']))
                ->setCellValue('C' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['Rejected']));
            $row++;
        }

        $sheet1->getColumnDimension('A')->setWidth(20);
        $sheet1->getColumnDimension('B')->setWidth(15);
        $sheet1->getColumnDimension('C')->setWidth(15);

        // Create a new sheet for Average session data
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Session Average');
        $sheet2->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Average Session Time');

        $row = 2;
        foreach ($sessionData as $data) {
            $sheet2->setCellValue('A' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['session_date']))
                ->setCellValue('B' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['average_time']));
            $row++;
        }

        $sheet2->getColumnDimension('A')->setWidth(20);
        $sheet2->getColumnDimension('B')->setWidth(15);

        // Create a new sheet for Total session data
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Session Total');
        $sheet3->setCellValue('A1', 'Date')
            ->setCellValue('B1', 'Total Session Time');

        $row = 2;
        foreach ($totalTimeData as $data) {
            $sheet3->setCellValue('A' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['session_date']))
                ->setCellValue('B' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['total_time']));
            $row++;
        }

        $sheet3->getColumnDimension('A')->setWidth(20);
        $sheet3->getColumnDimension('B')->setWidth(15);

        // Create a new sheet for Total Traffic data
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Total of Traffic');
        $sheet4->setCellValue('A1', 'Realm Name')
            ->setCellValue('B1', 'Uploads Flat')
            ->setCellValue('C1', 'Uploads')
            ->setCellValue('D1', 'Downloads Flat')
            ->setCellValue('E1', 'Downloads');

        $row = 2;
        foreach ($trafficData as $data) {
            $sheet4->setCellValue('A' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['realm']))
                ->setCellValue(
                    'B' . $row,
                    $escapeSpreadSheetService->escapeSpreadsheetValue($data['total_input_flat'])
                )
                ->setCellValue(
                    'C' . $row,
                    $escapeSpreadSheetService->escapeSpreadsheetValue($data['total_input'])
                )
                ->setCellValue(
                    'D' . $row,
                    $escapeSpreadSheetService->escapeSpreadsheetValue($data['total_output_flat'])
                )
                ->setCellValue(
                    'E' . $row,
                    $escapeSpreadSheetService->escapeSpreadsheetValue($data['total_output'])
                );
            $row++;
        }

        $sheet4->getColumnDimension('A')->setWidth(20);
        $sheet4->getColumnDimension('B')->setWidth(20);
        $sheet4->getColumnDimension('C')->setWidth(20);
        $sheet4->getColumnDimension('D')->setWidth(20);
        $sheet4->getColumnDimension('E')->setWidth(20);

        // Create a new sheet for Realm Usage data
        $sheet5 = $spreadsheet->createSheet();
        $sheet5->setTitle('Realm Usage');
        $sheet5->setCellValue('A1', 'Realm Name')
            ->setCellValue('B1', 'Usage');

        $row = 2;
        foreach ($realmUsageData as $data) {
            $sheet5->setCellValue('A' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['realm']))
                ->setCellValue('B' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['total_count']));
            $row++;
        }

        $sheet5->getColumnDimension('A')->setWidth(20);
        $sheet5->getColumnDimension('B')->setWidth(20);

        // Create a new sheet for Access Points Usage data
        $sheet6 = $spreadsheet->createSheet();
        $sheet6->setTitle('Access Points Usage');
        $sheet6->setCellValue('A1', 'MAC ADDRESS:SSID')
            ->setCellValue('B1', 'Usage');

        $row = 2;
        foreach ($apUsageData as $data) {
            $sheet6->setCellValue('A' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['ap_Name']))
                ->setCellValue('B' . $row, $escapeSpreadSheetService->escapeSpreadsheetValue($data['ap_Usage']));
            $row++;
        }

        $sheet6->getColumnDimension('A')->setWidth(40);
        $sheet6->getColumnDimension('B')->setWidth(20);

        // Create a new sheet for Wifi Standards Usage data
        $sheet7 = $spreadsheet->createSheet();
        $sheet7->setTitle('Wifi Standards Usage');
        $sheet7->setCellValue('A1', 'Wifi Standard Name')
            ->setCellValue('B1', 'Usage');

        $row = 2;
        foreach ($wifiStandardsData as $data) {
            $sheet7->setCellValue(
                'A' . $row,
                $escapeSpreadSheetService->escapeSpreadsheetValue($data['wifi_Standards'])
            )
                ->setCellValue(
                    'B' . $row,
                    $escapeSpreadSheetService->escapeSpreadsheetValue($data['wifi_Usage'])
                );
            $row++;
        }

        $sheet7->getColumnDimension('A')->setWidth(20);
        $sheet7->getColumnDimension('B')->setWidth(20);

        // Save the spreadsheet to a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'freeradius_statistics') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'uuid' => $currentUser->getUuid(),
        ];
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::EXPORT_FREERADIUS_STATISTICS_REQUEST->value,
            new DateTime(),
            $eventMetadata
        );


        return $this->file($tempFile, 'freeradiusStatistics.xlsx');
    }
}
