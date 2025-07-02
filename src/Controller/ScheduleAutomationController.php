<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\CronExpressionHelperService;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        $initialData["use_advanced_mode"] = $data["CRON_ADVANCE_STATUS"] ?? OperationMode::OFF->value;

        foreach ($this->cronSettings as $settingName) {
            $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
            $cronValue = $setting ? $setting->getValue() : '';

            $initialData["{$settingName}_advanced"] = $cronValue;

            $result = $this->cronExpressionHelperService->recognizeCronFrequency($cronValue);
            $parts = $result['parts'] ?? [];

            // Extract representative time from minutes/hours (ignoring frequency)
            $hourValues = $parts['hour']['values'] ?? [];
            $minuteValues = $parts['minute']['values'] ?? [];

            $hour = is_array($hourValues) && count($hourValues) > 0 ? (int)min($hourValues) : 0;
            $minute = is_array($minuteValues) && count($minuteValues) > 0 ? (int)min($minuteValues) : 0;

            try {
                $initialData["{$settingName}_time"] = new DateTimeImmutable()->setTime($hour, $minute);
            } catch (\Exception $e) {
                $initialData["{$settingName}_time"] = new DateTimeImmutable('00:00');
            }

            // Helper function to map cron part name to form field suffix
            $mapFieldKey = static fn(string $cronField) => match ($cronField) {
                'day_of_week' => 'day_of_week',
                'day_of_month' => 'day_of_month',
                'month' => 'months_of_the_year',
                default => $cronField,
            };

            // Helper function to expand cron expressions like "28-31/2" into [28, 30]
            // Handle day_of_week, day_of_month, and month values and frequencies
            foreach (['day_of_week', 'day_of_month', 'month'] as $field) {
                $fieldKey = $mapFieldKey($field);
                $raw = (string)($parts[$field]['raw'] ?? '*');

                // Set min and max depending on field
                switch ($field) {
                    case 'day_of_week':
                        $min = 0;
                        $max = 6; // Sunday=0 to Saturday=6
                        break;
                    case 'day_of_month':
                        $min = 1;
                        $max = 31;
                        break;
                    case 'month':
                        $min = 1;
                        $max = 12; // January=1 to December=12
                        break;
                    default:
                        $min = 0;
                        $max = 59;
                }

                if ($raw === '*' || $raw === '') {
                    // Set "All" when '*' is in the array so the form ChoiceType can show "All" option checked
                    $initialData["{$settingName}_{$fieldKey}"] = ['*'];
                } else {
                    // Expand expression like "28-31/2" on the array of values
                    $initialData["{$settingName}_{$fieldKey}"] = $this->expandCronPart($raw, $min, $max);
                }

                $initialData["{$settingName}_{$fieldKey}_frequency"] = $parts[$field]['frequency'] ?? 1;
            }
        }

        $form = $this->createForm(ScheduleType::class, $initialData, [
            'settings' => $this->settingRepository->findAll(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $useAdvancedMode = $form->get('use_advanced_mode')->getData();

            foreach ($this->cronSettings as $settingName) {
                if ($useAdvancedMode) {
                    $cronValue = $form->get("{$settingName}_advanced")->getData();
                } else {
                    $time = $form->get("{$settingName}_time")->getData();
                    $daysOfWeek = $form->get("{$settingName}_day_of_week")->getData();
                    $daysOfMonth = $form->get("{$settingName}_day_of_month")->getData();
                    $monthsOfYear = $form->get("{$settingName}_months_of_the_year")->getData();

                    // Frequencies
                    $dayOfWeekFreq = (int)$form->get("{$settingName}_day_of_week_frequency")->getData();
                    $dayOfMonthFreq = (int)$form->get("{$settingName}_day_of_month_frequency")->getData();
                    $monthsFreq = (int)$form->get("{$settingName}_months_of_the_year_frequency")->getData();

                    // Prevent both day_of_month and day_of_week frequencies being > 1 at the same time

                    // Validate frequency logic: frequency must be lower than the number of selected values
                    $fieldsToCheck = [
                        'day_of_week' => [$daysOfWeek, $dayOfWeekFreq],
                        'day_of_month' => [$daysOfMonth, $dayOfMonthFreq],
                        'months_of_the_year' => [$monthsOfYear, $monthsFreq],
                    ];

                    $validationFailed = false;
                    foreach ($fieldsToCheck as $fieldSuffix => [$selectedValues, $frequency]) {
                        if ($frequency > 1 && !in_array('*', $selectedValues, true)) {
                            $countSelected = count($selectedValues);
                            if ($frequency >= $countSelected) {
                                $form->get("{$settingName}_{$fieldSuffix}_frequency")->addError(
                                    new FormError(
                                        sprintf(
                                            'Frequency (%d) must be less than the number of selected values (%d).',
                                            $frequency,
                                            $countSelected
                                        )
                                    )
                                );
                                $validationFailed = true;
                            }
                        }
                    }

                    if ($validationFailed) {
                        // Stop processing this setting if validation failed
                        continue;
                    }

                    $hour = $time instanceof DateTimeInterface ? $time->format('H') : '0';
                    $minute = $time instanceof DateTimeInterface ? $time->format('i') : '0';

                    // Build the cron parts with frequency applied, e.g., day_of_week "1-15/2,20"
                    $dayOfMonthExpr = $this->cronExpressionHelperService->selectAllWithFreqConverter(
                        $daysOfMonth,
                        $dayOfMonthFreq
                    );
                    $monthExpr = $this->cronExpressionHelperService->selectAllWithFreqConverter(
                        $monthsOfYear,
                        $monthsFreq
                    );
                    $dayOfWeekExpr = $this->cronExpressionHelperService->selectAllWithFreqConverter(
                        $daysOfWeek,
                        $dayOfWeekFreq
                    );

                    $cronValue = "{$minute} {$hour} {$dayOfMonthExpr} {$monthExpr} {$dayOfWeekExpr}";
                }

                $this->saveSetting($settingName, $cronValue, $useAdvancedMode);
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

        return $this->render('admin/settings_actions.html.twig', [
            'user' => $currentUser,
            'data' => $data,
            'settings' => $this->settingRepository->findAll(),
            'form' => $form->createView(),
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

    private function expandCronPart(string $expr, int $min, int $max): array
    {
        if ($expr === '*' || $expr === '') {
            // Wildcard means all possible values
            return range($min, $max);
        }

        $result = [];

        // Split comma-separated parts
        foreach (explode(',', $expr) as $part) {
            $step = 1;

            // Check for step, e.g. "28-31/2"
            if (str_contains($part, '/')) {
                [$rangePart, $stepPart] = explode('/', $part, 2);
                $step = (int)$stepPart;
            } else {
                $rangePart = $part;
            }

            // Determine range or single value
            if ($rangePart === '*') {
                $rangeStart = $min;
                $rangeEnd = $max;
            } elseif (str_contains($rangePart, '-')) {
                [$rangeStart, $rangeEnd] = explode('-', $rangePart, 2);
                $rangeStart = (int)$rangeStart;
                $rangeEnd = (int)$rangeEnd;
            } else {
                $rangeStart = $rangeEnd = (int)$rangePart;
            }

            // Add values in the range with the step
            for ($i = $rangeStart; $i <= $rangeEnd; $i += $step) {
                if ($i >= $min && $i <= $max) {
                    $result[] = $i;
                }
            }
        }

        // Remove duplicates and sort values
        return array_values(array_unique($result));
    }
}
