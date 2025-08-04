<?php

namespace App\Controller;

use App\DTO\ScheduleDTO;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
use App\Service\CronExpressionHelperService;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ScheduleAutomationController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly CronExpressionHelperService $cronExpressionHelperService
    ) {
    }

    #[Route('/dashboard/settings/schedule', name: 'admin_dashboard_settings_schedule')]
    #[IsGranted('ROLE_ADMIN')]
    public function settingsSchedule(Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data = $this->getSettings->getSettings();

        $scheduleDTO = new ScheduleDTO($this->settingRepository, $this->cronExpressionHelperService);

        $form = $this->createForm(ScheduleType::class, $scheduleDTO);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach (
                $scheduleDTO->toCronExpressions(
                    $this->cronExpressionHelperService
                ) as $settingName => $cronExpression
            ) {
                $this->saveSetting($settingName, $cronExpression, $scheduleDTO->use_advanced_mode);
            }

            // Analytics
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_SCHEDULE_CONF_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->entityManager->flush();

            $this->addFlash(
                'success_admin',
                'New Schedule configuration has been applied successfully.'
            );
            return $this->redirectToRoute('admin_dashboard_settings_schedule');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $this->settingRepository->findAll(),
            'form' => $form->createView(),
            'formDTO' => $scheduleDTO,
        ]);
    }

    /**
     * Save or update a setting by name.
     */
    private function saveSetting(string $name, ?string $value, bool $advancedMode): void
    {
        $setting = $this->settingRepository->findOneBy(['name' => $name]);
        if ($setting !== null) {
            $setting->setValue($value);
            $this->entityManager->persist($setting);
        }
        $advancedModeStatus = $this->settingRepository->findOneBy(['name' => 'CRON_ADVANCED_STATUS']);
        $advancedModeValue = $advancedMode ? OperationMode::ON->value : OperationMode::OFF->value;
        if ($advancedModeStatus !== null) {
            $advancedModeStatus->setValue($advancedModeValue);
            $this->entityManager->persist($advancedModeStatus);
        }
    }
}
