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
use Symfony\Component\Lock\LockFactory;

/**
 * Enforces the password reset limits (cooldown between requests + max attempts per window) and records
 * the attempt in one atomic step per user.
 *
 * Uses the same settings and the same event type as the web forgot-password flow
 * (ForgotPasswordService::userCanResetPassword), so the web and the API share one counter: switching
 * channel does not reset the limit.
 *
 * The check and the insert of the attempt event run under a per-user lock, so concurrent requests are
 * serialised: the second one sees the event written by the first and is rejected by the cooldown.
 * The lock store comes from LOCK_DSN (flock by default, which is per host; a portal running several web
 * containers needs a shared store such as Redis or the database for this to hold across containers).
 */
final readonly class PasswordResetAttemptGuard
{
    public const string ALLOWED = 'allowed';
    public const string REASON = 'reason';
    public const string ATTEMPTS_LEFT = 'attemptsLeft';

    private const int LOCK_TTL_SECONDS = 30;

    public function __construct(
        private LockFactory $lockFactory,
        private ForgotPasswordService $forgotPasswordService,
        private EventRepository $eventRepository,
        private GetSettings $getSettings,
    ) {
    }

    /**
     * Checks the limits and, when the request is allowed, records the attempt before returning.
     * Callers must only change the password / send the SMS or email when "allowed" is true.
     *
     * @return array{allowed: bool, reason: string, attemptsLeft: int}
     *
     * @throws \DateMalformedStringException
     */
    public function tryRegisterAttempt(User $user, bool $isSms, ?string $clientIp, ?string $userAgent): array
    {
        $eventType = $isSms
            ? AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST->value
            : AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value;

        $lock = $this->lockFactory->createLock(
            sprintf('password-reset-%s-%d', $isSms ? 'sms' : 'email', (int)$user->getId()),
            self::LOCK_TTL_SECONDS
        );

        // Blocking: a concurrent request for the same user waits here instead of passing the check too
        $lock->acquire(true);

        try {
            $check = $this->forgotPasswordService->userCanResetPassword($user, $isSms);

            if (!$check[ForgotPasswordEnum::SUCCESS->value]) {
                return [
                    self::ALLOWED => false,
                    self::REASON => $check[ForgotPasswordEnum::MESSAGE_TYPE->value],
                    self::ATTEMPTS_LEFT => 0,
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
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{0: DateTime, 1: int} start of the attempts window and the max attempts in it
     * @throws \DateMalformedStringException
     */
    private function window(bool $isSms): array
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $resetAfter = $isSms
            ? SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS->value
            : SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value;
        $attemptsNumber = $isSms
            ? SettingName::SMS_ATTEMPTS_NUMBER->value
            : SettingName::EMAIL_ATTEMPTS_NUMBER->value;

        $windowStart = new DateTime();
        $windowStart->modify('-' . (int)$data[$resetAfter]['value'] . ' minutes');

        return [$windowStart, (int)$data[$attemptsNumber]['value']];
    }
}
