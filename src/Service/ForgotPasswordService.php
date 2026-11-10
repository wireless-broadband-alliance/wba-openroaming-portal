<?php

namespace App\Service;

use App\DTO\ForgotPasswordDTO;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\ForgotPasswordEnum;
use App\Enum\SettingName;
use App\Repository\EventRepository;
use DateMalformedStringException;
use DateTime;
use RuntimeException;

use function Symfony\Component\Clock\now;

readonly class ForgotPasswordService
{
    public function __construct(
        private EventRepository $eventRepository,
        private GetSettings $getSettings,
    ) {
    }

    /**
     * @return array{
     *      success: bool,
     *      messageType: string,
     *      timeLeft: mixed
     *  }
     * @throws DateMalformedStringException
     */
    public function userCanResetPassword(User $user, bool $isSMS = false): array
    {
        if ($isSMS) {
            $eventType = AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST->value;
            $timeToResetAttemptsType = SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS->value;
            $timeBetweenRequestsType = SettingName::SMS_TIME_INTERVAL_BETWEEN_REQUESTS->value;
            $attemptsNumberType = SettingName::SMS_ATTEMPTS_NUMBER->value;
        } else {
            $eventType = AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value;
            $timeToResetAttemptsType = SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value;
            $timeBetweenRequestsType = SettingName::EMAIL_TIME_INTERVAL_BETWEEN_REQUESTS->value;
            $attemptsNumberType = SettingName::EMAIL_ATTEMPTS_NUMBER->value;
        }

        // getSettings() returns an empty array on /api routes, so read only the limits we need straight from the
        // Setting table: this service is shared by the web forgot-password flow and the API reset endpoints
        $data = $this->getRequiredSettings(
            [$timeToResetAttemptsType, $timeBetweenRequestsType, $attemptsNumberType]
        );
        $event = $this->eventRepository->findLatestRequestAttemptEvent($user, $eventType);
        if ($event instanceof Event) {
            $limitTimeToReset = new DateTime();
            $timeToResetAttempts = $data[$timeToResetAttemptsType]['value'];
            $limitTimeToReset->modify('-' . $timeToResetAttempts . ' minutes');
            $events = $this->eventRepository->findLastEvents($user, $eventType, $limitTimeToReset);
            $attemptsNumber = (int)$data[$attemptsNumberType]['value'];
            if (count($events) < $attemptsNumber) {
                $lastEventTime = $event->getEventDatetime();
                $limitTime = new DateTime();
                $timeBetweenRequests = $data[$timeBetweenRequestsType]['value'];
                $limitTime->modify('-' . $timeBetweenRequests . ' seconds');
                if ($limitTime > $lastEventTime) {
                    return [
                        ForgotPasswordEnum::SUCCESS->value => true,
                        ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::SUCCESS->value,
                        ForgotPasswordEnum::TIME_LEFT->value => 0,
                    ];
                }
                $timeLeft = $limitTime->diff($lastEventTime);
                return [
                    ForgotPasswordEnum::SUCCESS->value => false,
                    ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::TIME_BETWEEN_REQUESTS->value,
                    ForgotPasswordEnum::TIME_LEFT->value => $timeLeft,
                ];
            }
            $firstEvent = $events[$attemptsNumber - 1];
            $firstEventTime = $firstEvent->getEventDatetime();
            $timeLeft = $limitTimeToReset->diff($firstEventTime ?? now());
            return [
                ForgotPasswordEnum::SUCCESS->value => false,
                ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::ATTEMPTS_EXCEEDED->value,
                ForgotPasswordEnum::TIME_LEFT->value => $timeLeft,
            ];
        }
        return [
            ForgotPasswordEnum::SUCCESS->value => true,
            ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::SUCCESS->value,
            ForgotPasswordEnum::TIME_LEFT->value => 0,
        ];
    }

    /**
     * Reads the given settings directly from the database, whatever the current route is.
     *
     * @param string[] $names
     *
     * @return array<string, array{value: string}>
     *
     * @throws RuntimeException when one of the settings is missing from the database
     */
    public function getRequiredSettings(array $names): array
    {
        $data = $this->getSettings->getSpecificSettings($names);

        $missing = array_diff($names, array_keys($data));
        if ($missing !== []) {
            throw new RuntimeException(
                'Missing required password reset settings: ' . implode(', ', $missing)
            );
        }

        return $data;
    }
}
