<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\CronExpressionHelperService;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ScheduleAutomationController extends AbstractController
{
    private array $cronSettings = [
        'DELETE_UNCONFIRMED_USERS_CRON',
        'USERS_WHEN_PROFILE_EXPIRES_CRON',
        'LDAP_SYNC_CRON',
    ];

    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly CronExpressionHelperService $cronExpressionHelperService
    ) {
    }

    #[Route('/dashboard/settings/schedule', name: 'admin_dashboard_settings_schedule')]
    public function settingsSchedule(Request $request): Response
    {
        // Load all settings data for current user
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Prepare initial form data by decoding existing cron expressions
        $initialData = [];
        foreach ($this->cronSettings as $settingName) {
            $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
            $cronValue = $setting ? $setting->getValue() : '';
            $initialData["{$settingName}_advanced"] = $cronValue;

            $result = $this->cronExpressionHelperService->recognizeCronFrequency($cronValue);

            if ($result['frequency'] === 'daily') {
                $initialData["{$settingName}_frequency"] = 'daily';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat('H:i', $result['time']);
                $initialData["{$settingName}_day_of_week"] = [];
                $initialData["{$settingName}_day_of_month"] = [];
            } elseif ($result['frequency'] === 'weekly') {
                $initialData["{$settingName}_frequency"] = 'weekly';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat('H:i', $result['time']);
                $initialData["{$settingName}_day_of_week"] = array_map('intval', $result['day_of_week']);
                $initialData["{$settingName}_day_of_month"] = [];
            } elseif ($result['frequency'] === 'monthly') {
                $initialData["{$settingName}_frequency"] = 'monthly';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat('H:i', $result['time']);
                $initialData["{$settingName}_day_of_month"] = array_map('intval', $result['day_of_month']);
                $initialData["{$settingName}_day_of_week"] = [];
            } else {
                // Advanced mode or unknown, set empty defaults
                $initialData["{$settingName}_frequency"] = $this->cronExpressionHelperService->guessFrequencyFromParts(
                    $result['parts']
                );
                $initialData["{$settingName}_time"] = DateTime::createFromFormat('H:i', $result['time']);
                $initialData["{$settingName}_day_of_week"] = $result['parts']['day_of_week']['values'] ?? [];
                $initialData["{$settingName}_day_of_month"] = $result['parts']['day_of_month']['values'] ?? [];
            }
        }

        $settings = $this->settingRepository->findAll();

        $form = $this->createForm(ScheduleType::class, $initialData, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $useAdvancedMode = $form->get('use_advanced_mode')->getData();

            foreach ($this->cronSettings as $settingName) {
                $cronValue = '';

                if ($useAdvancedMode) {
                    // Use manual cron input
                    $cronValue = $form->get("{$settingName}_advanced")->getData() ?? '';
                } else {
                    // Build cron expression from selected frequency and time
                    $frequency = $form->get("{$settingName}_frequency")->getData();
                    $time = $form->get("{$settingName}_time")->getData();

                    if ($frequency && $time instanceof DateTimeInterface) {
                        $hour = $time->format('H');
                        $minute = $time->format('i');

                        switch ($frequency) {
                            case 'daily':
                                $cronValue = "$minute $hour * * *";
                                break;

                            case 'weekly':
                                $daysOfWeek = $form->get("{$settingName}_day_of_week")->getData(
                                ) ?: [0]; // fallback Sunday
                                $cronValue = "$minute $hour * * " . implode(',', $daysOfWeek);
                                break;

                            case 'monthly':
                                $daysOfMonth = $form->get("{$settingName}_day_of_month")->getData(
                                ) ?: [1]; // fallback 1st
                                $cronValue = "$minute $hour " . implode(',', $daysOfMonth) . " * *";
                                break;
                        }
                    }
                }

                // Retrieve additional schedule info
                $startDate = $form->get("{$settingName}_startDate")->getData();
                $endDate = $form->get("{$settingName}_endDate")->getData();
                $interval = $form->get("{$settingName}_interval")->getData();

                // Save CRON value
                $this->saveSetting("{$settingName}_cron", $cronValue);

                // Save interval
                $this->saveSetting("{$settingName}_interval", $interval?->format('H:i'));

                // Save start date
                $this->saveSetting("{$settingName}_startDate", $startDate?->format(DateTimeInterface::ATOM));

                // Save end date
                $this->saveSetting("{$settingName}_endDate", $endDate?->format(DateTimeInterface::ATOM));
            }

            // Save event for analytics
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SCHEDULE_CONF_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );

            $this->entityManager->flush();

            $this->addFlash('success_admin', 'New Schedule configuration has been applied successfully.');

            return $this->redirectToRoute('admin_dashboard_settings_schedule');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $settings,
            'form' => $form->createView(),
        ]);
    }

    private function saveSetting(string $name, ?string $value): void
    {
        $setting = $this->settingRepository->findOneBy(['name' => $name]);
        if ($setting !== null) {
            $setting->setValue($value);
            $this->entityManager->persist($setting);
        }
    }
}
