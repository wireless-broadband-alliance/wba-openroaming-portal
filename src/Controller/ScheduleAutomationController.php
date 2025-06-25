<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use Cron\CronExpression;
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

        foreach (
            [
                'DELETE_UNCONFIRMED_USERS_CRON',
                'USERS_WHEN_PROFILE_EXPIRES_CRON',
                'LDAP_SYNC_CRON'
            ] as $settingName
        ) {
            $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
            $cronValue = $setting ? $setting->getValue() : '';
            $initialData["{$settingName}_advanced"] = $cronValue;

            $cron = CronExpression::factory($cronValue);

            $parts = preg_split('/\s+/', $cron);

            [$minutes, $hours, $days, $months, $dayOfWeek] = $parts;

            if ($days === '*' && $dayOfWeek === '*') {
                // daily: "minute hour * * *"

                $initialData["{$settingName}_frequency"] = 'daily';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat(
                    'H:i',
                    sprintf('%02d:%02d', $hours, $minutes)
                );
                $initialData["{$settingName}_day_of_week"] = [];
                $initialData["{$settingName}_day_of_month"] = [];
            } elseif ($days === '*' && $dayOfWeek !== '*') {
                // weekly: "minute hour * * day_of_week"

                $initialData["{$settingName}_frequency"] = 'weekly';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat(
                    'H:i',
                    sprintf('%02d:%02d', $hours, $minutes)
                );
                $initialData["{$settingName}_day_of_week"] = [(int)$dayOfWeek];
                $initialData["{$settingName}_day_of_month"] = [];
            } elseif ($days !== '*' && $dayOfWeek === '*') {
                // monthly: "minute hour day_of_month * *"

                $initialData["{$settingName}_frequency"] = 'monthly';
                $initialData["{$settingName}_time"] = DateTime::createFromFormat(
                    'H:i',
                    sprintf('%02d:%02d', $hours, $minutes)
                );
                $initialData["{$settingName}_day_of_month"] = [(int)$days];
                $initialData["{$settingName}_day_of_week"] = [];
            } else {
                // advanced or unrecognized
                $initialData["{$settingName}_frequency"] = null;
                $initialData["{$settingName}_time"] = null;
                $initialData["{$settingName}_day_of_week"] = [];
                $initialData["{$settingName}_day_of_month"] = [];
            }
        }

        $settings = $this->settingRepository->findAll();
        $form = $this->createForm(ScheduleType::class, $initialData, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);
        dump($form->getData());
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
                    $cronValue = $form->get($settingName . '_advanced')->getData() ?: '';
                } else {
                    $frequency = $form->get($settingName . '_frequency')->getData();
                    $time = $form->get($settingName . '_time')->getData();

                    if ($frequency && $time instanceof DateTimeInterface) {
                        $hour = $time->format('H');
                        $minute = $time->format('i');

                        if ($frequency === 'daily') {
                            $cronValue = "$minute $hour * * *";
                        } elseif ($frequency === 'weekly') {
                            $dayOfWeek = $form->get($settingName . '_day_of_week')->getData();
                            // default to Sunday if not set
                            $dayOfWeek ??= 0;
                            $cronValue = "$minute $hour * * $dayOfWeek";
                        } elseif ($frequency === 'monthly') {
                            $dayOfMonth = $form->get($settingName . '_day_of_month')->getData();
                            // default to 1 if not set
                            $dayOfMonth ??= 1;
                            $cronValue = "$minute $hour $dayOfMonth * *";
                        }
                    }
                }

                $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
                if ($setting !== null) {
                    $setting->setValue($cronValue);
                    $this->entityManager->persist($setting);
                }
            }

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
