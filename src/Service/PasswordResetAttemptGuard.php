<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\ForgotPasswordEnum;
use App\Enum\SettingName;
use App\Repository\EventRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The single check-and-record operation for every password reset channel: the web forgot-password pages
 * (email and SMS) and the API v3/v4 reset endpoints (/auth/local/reset and /auth/sms/reset). No reset
 * channel may check the limits or write a reset attempt event on its own.
 *
 * It enforces the limits (cooldown between requests + max attempts per window) with the same settings and
 * the same event types as ForgotPasswordService::userCanResetPassword, so every channel shares one counter:
 * switching channel does not reset the limit.
 *
 * Atomicity: the check and the insert of the attempt event run in one database transaction that first takes
 * a row lock on the user (SELECT ... FOR UPDATE). Every channel and every web container uses the same
 * database, so concurrent requests for the same user are serialised by the database itself, whatever the
 * deployment (one or several web containers) and without depending on a shared lock store: the second
 * request waits for the first to commit, then sees its event and is refused by the cooldown.
 */
final readonly class PasswordResetAttemptGuard
{
    public const string ALLOWED = 'allowed';
    public const string REASON = 'reason';
    public const string ATTEMPTS_LEFT = 'attemptsLeft';
    public const string TIME_LEFT = 'timeLeft';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ForgotPasswordService $forgotPasswordService,
        private EventRepository $eventRepository,
    ) {
    }

    /**
     * Checks the limits and, when the request is allowed, records the attempt before returning.
     * Callers must only change the password / send the SMS or email when "allowed" is true.
     *
     * "timeLeft" is how long the user has to wait when the request is refused (a DateInterval from
     * ForgotPasswordService), or 0 when it is allowed.
     *
     * @return array{allowed: bool, reason: string, attemptsLeft: int, timeLeft: mixed}
     */
    public function tryRegisterAttempt(User $user, bool $isSms, ?string $clientIp, ?string $userAgent): array
    {
        /** @var array{allowed: bool, reason: string, attemptsLeft: int, timeLeft: mixed} $result */
        return $this->entityManager->wrapInTransaction(
            function () use ($user, $isSms, $clientIp, $userAgent): array {
                // Row lock on the user, held until the transaction commits: a concurrent reset for the same
                // user, from any channel or container, blocks here until this one has written its event
                $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);

                return $this->checkAndRecord($user, $isSms, $clientIp, $userAgent);
            }
        );
    }

    /**
     * Must only run inside the locked transaction of tryRegisterAttempt().
     *
     * @return array{allowed: bool, reason: string, attemptsLeft: int, timeLeft: mixed}
     *
     * @throws \DateMalformedStringException
     */
    private function checkAndRecord(User $user, bool $isSms, ?string $clientIp, ?string $userAgent): array
    {
        $eventType = $isSms
            ? AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST->value
            : AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value;

        $check = $this->forgotPasswordService->userCanResetPassword($user, $isSms);

        if (!$check[ForgotPasswordEnum::SUCCESS->value]) {
            return [
                self::ALLOWED => false,
                self::REASON => $check[ForgotPasswordEnum::MESSAGE_TYPE->value],
                self::ATTEMPTS_LEFT => 0,
                self::TIME_LEFT => $check[ForgotPasswordEnum::TIME_LEFT->value],
            ];
        }

        [$windowStart, $maxAttempts] = $this->window($isSms);
        $previousAttempts = count($this->eventRepository->findLastEvents($user, $eventType, $windowStart));
        $now = new DateTime();

        // Timestamp and counter are written together, in the same event, before any side effect
        $event = new Event();
        $event->setUser($user);
        $event->setEventDatetime($now);
        $event->setEventName($eventType);
        $event->setEventMetadata([
            EventMetadataKeysType::IP->value => $clientIp,
            EventMetadataKeysType::USER_AGENT->value => $userAgent,
            EventMetadataKeysType::UUID->value => $user->getUuid(),
            'lastVerificationCodeTime' => $now->format(DateTimeInterface::ATOM),
            'verificationAttempts' => $previousAttempts + 1,
        ]);
        $this->eventRepository->save($event, true);

        return [
            self::ALLOWED => true,
            self::REASON => ForgotPasswordEnum::SUCCESS->value,
            self::ATTEMPTS_LEFT => max(0, $maxAttempts - ($previousAttempts + 1)),
            self::TIME_LEFT => 0,
        ];
    }

    /**
     * @return array{0: DateTime, 1: int} start of the attempts window and the max attempts in it
     * @throws \DateMalformedStringException
     */
    private function window(bool $isSms): array
    {
        $resetAfter = $isSms
            ? SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS->value
            : SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value;
        $attemptsNumber = $isSms
            ? SettingName::SMS_ATTEMPTS_NUMBER->value
            : SettingName::EMAIL_ATTEMPTS_NUMBER->value;

        // Not GetSettings::getSettings(): it returns an empty array on /api routes, where this guard also runs
        $data = $this->forgotPasswordService->getRequiredSettings([$resetAfter, $attemptsNumber]);

        $windowStart = new DateTime();
        $windowStart->modify('-' . (int)$data[$resetAfter]['value'] . ' minutes');

        return [$windowStart, (int)$data[$attemptsNumber]['value']];
    }
}
