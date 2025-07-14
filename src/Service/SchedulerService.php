<?php

namespace App\Service;


use App\Enum\OperationMode;
use App\Repository\SettingRepository;

readonly class SchedulerService
{
    public function __construct(
        private SettingRepository $settingRepository,
        private CronExpressionHelperService $cronExpressionHelperService
    ) {
    }

    public function verifyHoursAndMinutesFrequency(string $cron): ?string
    {
        $advancedMode = $this->settingRepository->findOneBy(['name' => 'CRON_ADVANCED_STATUS']);
        if ($advancedMode && $advancedMode->getValue() === OperationMode::OFF->value) {
            return null;
        }
        $result = $this->cronExpressionHelperService->recognizeCronFrequency($cron);
        $parts = $result['parts'] ?? [];
        if ($parts['minute']['frequency'] > 1 && $parts['hour']['frequency'] > 1) {
            return 'minutes and hours';
        }
        if ($parts['minute']['frequency'] > 1) {
            return 'minutes';
        }
        if ($parts['hour']['frequency'] > 1) {
            return 'hours';
        }
        return null;
    }
}
