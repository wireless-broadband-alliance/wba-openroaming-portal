<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\TimeRangePresetStatistics;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\GetSettings;
use App\Service\Statistics\Portal\PortalStatistics;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly PortalStatistics $portalStatistics,
    ) {
    }

    /**
     * Render Statistics about the Portal data
     */
    /**
     * @throws JsonException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    #[Route('/dashboard/statistics', name: 'admin_dashboard_statistics')]
    #[IsGranted(UserAuthenticationVoter::PORTAL_STATISTICS_READ)]
    public function statisticsData(Request $request): Response
    {
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Get the submitted start and end dates from the form
        $startDateString = $request->query->get('startDate');
        $endDateString = $request->query->get('endDate');

        // Convert the date strings to DateTime objects
        $startDate = $startDateString ? new DateTime($startDateString) : new DateTime()->modify(
            '-1 week'
        );

        $endDate = $endDateString ? new DateTime($endDateString) : new DateTime();

        $interval = $startDate->diff($endDate);

        if ($interval->days > 366) {
            $this->addFlash(
                'error',
                $this->translator->trans('maximumDateRange1Year', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_statistics');
        }

        // After computing $startDate and $endDate, detect which preset was used
        $activePreset = $request->query->get('preset', '');
        $activePreset = TimeRangePresetStatistics::fromInput($activePreset ?? '');

        // If it resolved to Custom but there are no dates, fall back to default
        if ($activePreset === TimeRangePresetStatistics::Custom && !$startDateString && !$endDateString) {
            $activePreset = TimeRangePresetStatistics::default();
        }

        $fetchChartSMSEmail = $this->portalStatistics->getSMSEmailStats($startDate, $endDate);
        $fetchChartAuthentication = $this->portalStatistics->getAuthenticationStats($startDate, $endDate);
        $fetchChartDevices = $this->portalStatistics->getDevicesStats($startDate, $endDate);
        $fetchChartPlatformStatus = $this->portalStatistics->getPlatformStatusStats($startDate, $endDate);
        $fetchChartUserVerified = $this->portalStatistics->getUserVerifiedStas($startDate, $endDate);
        $fetchChart2FA = $this->portalStatistics->get2FAStats($startDate, $endDate);

        $memory_before = memory_get_usage();
        $memory_after = memory_get_usage();
        $memory_diff = $memory_after - $memory_before;

        // Check that the memory usage does not exceed the PHP memory limit of 128M
        if ($memory_diff > 134217728) {
            $this->addFlash(
                'error',
                $this->translator->trans('dataRequestedTooLarge', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_statistics');
        }

        return $this->render('dashboard/statistics/statistics.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'SMSEmailDataJson' => json_encode($fetchChartSMSEmail, JSON_THROW_ON_ERROR),
            'authenticationDataJson' => json_encode($fetchChartAuthentication, JSON_THROW_ON_ERROR),
            'devicesDataJson' => json_encode($fetchChartDevices, JSON_THROW_ON_ERROR),
            'platformStatusDataJson' => json_encode($fetchChartPlatformStatus, JSON_THROW_ON_ERROR),
            'usersVerifiedDataJson' => json_encode($fetchChartUserVerified, JSON_THROW_ON_ERROR),
            'twoFADataJson' => json_encode($fetchChart2FA, JSON_THROW_ON_ERROR),
            'selectedStartDate' => $startDate->format('Y-m-d\TH:i'),
            'selectedEndDate' => $endDate->format('Y-m-d\TH:i'),
            'activePreset' => $activePreset->value,
        ]);
    }
}
