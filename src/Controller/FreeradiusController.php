<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\FreeradiusConnectionService;
use App\Service\GetSettings;
use App\Service\Statistics\DashboardFormatter;
use App\Service\Statistics\Freeradius\ExportService;
use App\Service\Statistics\Freeradius\FreeradiusStatistics;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(UserAuthenticationVoter::CONNECTIVITY_STATISTICS_READ)]
class FreeradiusController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly FreeradiusConnectionService $freeradiusConnectionService,
        private readonly FreeradiusStatistics $statisticsFreeradius,
        private readonly DashboardFormatter $statisticsFreeradiusFormatter,
        private readonly ExportService $freeradiusExportService
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
    public function freeradiusStatisticsData(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] ?int $count = 5
    ): Response {
        $result = $this->freeradiusConnectionService->checkDBConnection();
        if ($result['success'] === false) {
            throw new ServiceUnavailableHttpException(
                null,
                'FreeRADIUS DB connection failed: ' . $result['message']
            );
        }

        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

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
                'error',
                $this->translator->trans(
                    'maximumDateRange1Year',
                    [],
                    'controllers'
                )
            );

            return $this->redirectToRoute('admin_dashboard_statistics_freeradius');
        }

        // Authentication Attempts
        $fetchChartAuthenticationsFreeradius = $this->statisticsFreeradius
            ->getAuthenticationStats($startDate, $endDate);

        // Session Average
        $fetchChartSessionAverageFreeradius = $this->statisticsFreeradius
            ->getSessionAverageStats($startDate, $endDate);

        // Session Total
        $fetchChartSessionTotalFreeradius = $this->statisticsFreeradius
            ->getSessionTotalStats($startDate, $endDate);

        // Traffic Stats
        $fetchChartTrafficFreeradius = $this->statisticsFreeradius
            ->getTrafficStats($startDate, $endDate);

        // Realms Usage
        $fetchChartRealmsFreeradius = $this->statisticsFreeradius
            ->getRealmUsageStats($startDate, $endDate);

        // Wifi Versions
        $fetchChartWifiTags = $this->statisticsFreeradius
            ->getWifiStats($startDate, $endDate);

        // Access Points Usage
        $fetchChartApUsage = $this->statisticsFreeradius
            ->getApUsageStats($startDate, $endDate);

        // Current Authenticated Users
        $fetchChartCurrentAuthFreeradius = $this->statisticsFreeradius
            ->getCurrentAuthStats();

        $memory_before = memory_get_usage();
        $memory_after = memory_get_usage();
        $memory_diff = $memory_after - $memory_before;

        // Check that the memory usage does not exceed the PHP memory limit of 128M
        if ($memory_diff > 134217728) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'maximumDateRange1Year',
                    [],
                    'controllers'
                )
            );

            return $this->redirectToRoute('admin_dashboard_statistics_freeradius');
        }

        // Extract the connection attempts
        $authCounts = [
            'Accepted' => array_sum(array_column($fetchChartAuthenticationsFreeradius, 'accepted')),
            'Rejected' => array_sum(array_column($fetchChartAuthenticationsFreeradius, 'rejected')),
        ];

        // Extract the average time
        $totalAverageTimeSeconds = $this->statisticsFreeradiusFormatter->sum(
            $fetchChartSessionAverageFreeradius
        );

        // Convert the total average time to human-readable format
        $totalAverageTimeReadable = sprintf(
            '%dh %dm',
            floor($totalAverageTimeSeconds / 3600),
            floor(($totalAverageTimeSeconds % 3600) / 60)
        );

        // Extract the total time
        $totalTimeSeconds = $this->statisticsFreeradiusFormatter->sum(
            $fetchChartSessionTotalFreeradius
        );

        // Convert the total time to human-readable format
        $totalTimeReadable = sprintf(
            '%dh %dm',
            floor($totalTimeSeconds / 3600),
            floor(($totalTimeSeconds % 3600) / 60)
        );

        // Sum all the traffic from the Accounting table
        $totalTraffic = $this->statisticsFreeradiusFormatter->formatTraffic(
            $fetchChartTrafficFreeradius
        );

        // Extract all realms names and usage
        $realmsUsage = [];
        foreach ($fetchChartRealmsFreeradius as $realm => $usage) {
            $realmsUsage[$realm] = ($realmsUsage[$realm] ?? 0) + $usage;
        }

        // Access Points Usage Count
        $totalApCount = count($fetchChartApUsage);
        $totalPages = ceil($totalApCount / $count);
        $offset = ($page - 1) * $count;
        $fetchChartApUsage = array_slice($fetchChartApUsage, $offset, $count);

        // Sum all the current authentication
        $totalCurrentAuths = array_sum($fetchChartCurrentAuthFreeradius);

        return $this->render('dashboard/statistics/freeradius_statistics.html.twig', [
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
    #[Route('/dashboard/statistics/freeradius/export', name: 'admin_dashboard_statistics_freeradius_export')]
    public function exportFreeradius(Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $startDate = $request->query->get('startDate')
            ? new DateTime($request->query->get('startDate'))
            : new DateTime('-1 week');

        $endDate = $request->query->get('endDate')
            ? new DateTime($request->query->get('endDate'))
            : new DateTime();

        $data = [
            'auth' => $this->statisticsFreeradius->getAuthenticationStats($startDate, $endDate),
            'sessionAvg' => $this->statisticsFreeradius->getSessionAverageStats($startDate, $endDate),
            'sessionTotal' => $this->statisticsFreeradius->getSessionTotalStats($startDate, $endDate),
            'traffic' => $this->statisticsFreeradius->getTrafficStats($startDate, $endDate),
            'realms' => $this->statisticsFreeradius->getRealmUsageStats($startDate, $endDate),
            'apUsage' => $this->statisticsFreeradius->getApUsageStats($startDate, $endDate),
            'wifi' => $this->statisticsFreeradius->getWifiStats($startDate, $endDate),
        ];

        // Export Excel ONLY
        $filePath = $this->freeradiusExportService->export($data);

        // Event log
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::EXPORT_FREERADIUS_STATISTICS_REQUEST->value,
            new DateTime(),
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ]
        );

        return $this->file($filePath, 'freeradiusStatistics.xlsx');
    }
}
