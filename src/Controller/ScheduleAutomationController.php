<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Form\CapportType;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Schedule;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ScheduleAutomationController extends AbstractController
{

    public function __construct(private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions
    )
    {
    }

    #[Route('/dashboard/settings/schedule', name: 'admin_dashboard_settings_schedule')]
    public function settingsSchedule(Request $request): Response
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $settings = $this->settingRepository->findAll();

        $form = $this->createForm(ScheduleType::class, null, [
            'settings' => $settings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            $settingsToUpdate = [
                'DELETE_UNCONFIRMED_USERS_CRON',
                'USERS_WHEN_PROFILE_EXPIRES_CRON',
                'LDAP_SYNC_CRON',
            ];

            foreach ($settingsToUpdate as $settingName) {
                $value = $submittedData[$settingName] ?? null;

                // Check if any submitted data is empty
                if ($value === null) {
                    $value = "";
                }

                $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
                if ($setting !== null) {
                    $setting->setValue($value);
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

            $this->addFlash('success_admin', 'New Schedule configuration have been applied successfully.');
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
