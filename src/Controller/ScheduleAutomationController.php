<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ScheduleAutomationController extends AbstractController
{

    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions
    ) {
    }

    #[Route('/dashboard/settings/schedule', name: 'admin_dashboard_settings_schedule')]
    public function settingsSchedule(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $initialData = [];

        // Loop through the settings you want to edit
        foreach (
            [
                'DELETE_UNCONFIRMED_USERS_CRON',
                'USERS_WHEN_PROFILE_EXPIRES_CRON',
                'LDAP_SYNC_CRON'
            ] as $settingName
        ) {
            // Find the setting value from the DB
            $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
            $cronValue = $setting ? $setting->getValue() : '';

            // Determine if the cronValue is an "advanced" expression or a simple "frequency + time"
            // Matches a known pattern we parse it, else fallback to advanced
            if (preg_match('/^\d+ \d+ \* \* \*$/', $cronValue)) {
                // This is a daily schedule example "minute hour * * *"
                // Parse minute and hour from the cronValue
                [$minute, $hour] = explode(' ', $cronValue);

                $initialData["{$settingName}_frequency"] = 'daily';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat(
                    'H:i',
                    sprintf('%02d:%02d', $hour, $minute)
                );
                $initialData["{$settingName}_advanced"] = null;
            } elseif (preg_match('/^\d+ \d+ \* \* 0$/', $cronValue)) {
                // weekly
                [$minute, $hour] = explode(' ', $cronValue);

                $initialData["{$settingName}_frequency"] = 'weekly';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat(
                    'H:i',
                    sprintf('%02d:%02d', $hour, $minute)
                );
                $initialData["{$settingName}_advanced"] = null;
            } elseif (preg_match('/^\d+ \d+ 1 \* \*$/', $cronValue)) {
                // monthly
                [$minute, $hour] = explode(' ', $cronValue);

                $initialData["{$settingName}_frequency"] = 'monthly';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat(
                    'H:i',
                    sprintf('%02d:%02d', $hour, $minute)
                );
                $initialData["{$settingName}_advanced"] = null;
            } else {
                // fallback: treat as advanced expression
                $initialData["{$settingName}_advanced"] = $cronValue;
                $initialData["{$settingName}_frequency"] = null;
                $initialData["{$settingName}_time"] = null;
            }
        }

        $settings = $this->settingRepository->findAll();
        $form = $this->createForm(ScheduleType::class, $initialData, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $useAdvancedMode = $form->get('use_advanced_mode')->getData();

            $settingsToUpdate = [
                'DELETE_UNCONFIRMED_USERS_CRON',
                'USERS_WHEN_PROFILE_EXPIRES_CRON',
                'LDAP_SYNC_CRON',
            ];

            foreach ($settingsToUpdate as $settingName) {
                $cronValue = '';

                if ($useAdvancedMode) {
                    // Detect if the user checked the simple/advanced mode
                    $cronValue = $form->get($settingName . '_advanced')->getData() ?: '';
                } else {
                    $frequency = $form->get($settingName . '_frequency')->getData();
                    $time = $form->get($settingName . '_time')->getData();

                    if ($frequency && $time instanceof DateTimeInterface) {
                        $hour = $time->format('H');
                        $minute = $time->format('i');

                        $cronValue = match ($frequency) {
                            'daily' => "$minute $hour * * *",
                            'weekly' => "$minute $hour * * 0",
                            'monthly' => "$minute $hour 1 * *",
                            default => '',
                        };
                    }
                }

                $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
                if ($setting !== null) {
                    $setting->setValue($cronValue);
                    $this->entityManager->persist($setting);
                }
            }

            // Track event
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
}
