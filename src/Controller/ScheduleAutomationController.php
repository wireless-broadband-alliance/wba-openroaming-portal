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
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $initialData = [];

        foreach ($this->cronSettings as $settingName) {
            $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
            $cronValue = $setting ? $setting->getValue() : '';
            $initialData["{$settingName}_advanced"] = $cronValue;

            $result = $this->cronExpressionHelperService->recognizeCronFrequency($cronValue);
            $parts = $result['parts'] ?? [];

            $initialData["{$settingName}_time"] = \DateTime::createFromFormat('H:i', $result['time'] ?? '00:00');
            $initialData["{$settingName}_day_of_week"] = $parts['day_of_week']['values'] ?? [];
            $initialData["{$settingName}_day_of_month"] = $parts['day_of_month']['values'] ?? [];
            $initialData["{$settingName}_months_of_the_year"] = $parts['month']['values'] ?? [];
        }

        $form = $this->createForm(ScheduleType::class, $initialData, [
            'settings' => $this->settingRepository->findAll(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $useAdvancedMode = $form->get('use_advanced_mode')->getData();

            foreach ($this->cronSettings as $settingName) {
                $cronValue = '';

                if ($useAdvancedMode) {
                    $cronValue = $form->get("{$settingName}_advanced")->getData();
                } else {
                    $time = $form->get("{$settingName}_time")->getData();
                    $daysOfWeek = $form->get("{$settingName}_day_of_week")->getData();
                    $daysOfMonth = $form->get("{$settingName}_day_of_month")->getData();
                    $monthsOfYear = $form->get("{$settingName}_months_of_the_year")->getData();

                    $hour = $time instanceof \DateTimeInterface ? $time->format('H') : '0';
                    $minute = $time instanceof \DateTimeInterface ? $time->format('i') : '0';

                    $dayOfMonthStr = !empty($daysOfMonth) ? implode(',', $daysOfMonth) : '*';
                    $monthStr = !empty($monthsOfYear) ? implode(',', $monthsOfYear) : '*';
                    $dayOfWeekStr = !empty($daysOfWeek) ? implode(',', $daysOfWeek) : '*';

                    $cronValue = "{$minute} {$hour} {$dayOfMonthStr} {$monthStr} {$dayOfWeekStr}";
                }

                $this->saveSetting($settingName, $cronValue);
            }

            // Analytics
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SCHEDULE_CONF_REQUEST->value,
                new \DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->entityManager->flush();

            $this->addFlash('success_admin', 'New Schedule configuration has been applied successfully.');
            return $this->redirectToRoute('admin_dashboard_settings_schedule');
        }

        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $this->settingRepository->findAll(),
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
