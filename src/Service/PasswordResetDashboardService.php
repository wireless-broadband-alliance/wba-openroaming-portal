<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\EventRepository;
use App\Repository\UserExternalAuthRepository;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumber;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class PasswordResetDashboardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailGenerator $emailGenerator,
        private SendSMS $sendSMS,
        private EventActions $eventActions,
        private EventRepository $eventRepository,
        private UserExternalAuthRepository $userExternalAuthRepository,
        private GetSettings $getSettings,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Resets the user's password, sends notifications, and logs the event.
     * Returns an array of flash messages: [['type' => 'success'|'error', 'message' => '...']]
     *
     * @return array<int, array{type: string, message: string}>
     * * @throws \DateMalformedStringException
     * * @throws \DateMalformedIntervalStringException
     * * @throws TransportExceptionInterface
     */
    public function resetPassword(
        User $user,
        string $newPassword,
        string $clientIp,
        ?string $userAgent,
        User $byUser
    ): array {
        $flashes = [];

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setForgotPasswordRequest(true);
        $this->entityManager->flush();

        if ($user->getEmail()) {
            $this->emailGenerator->sendResetPasswordEmailByAdmin($user, $newPassword);
            $this->eventActions->saveEvent(
                $byUser,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_DASHBOARD->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $clientIp,
                    EventMetadataKeysType::USER_AGENT->value => $userAgent,
                    EventMetadataKeysType::UUID->value => $byUser->getUuid(),
                    EventMetadataKeysType::PERFORMED_ON_UUID->value => $user->getUuid(),
                ]
            );
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        if ($user->getPhoneNumber() instanceof PhoneNumber &&
            $userExternalAuth?->getProviderId() === UserProvider::PHONE_NUMBER->value
        ) {
            return array_merge(
                $flashes,
                $this->handleSmsReset(
                    $user,
                    $newPassword,
                    $clientIp,
                    $byUser
                )
            );
        }

        return $flashes;
    }

    /**
     * @return array<int, array{type: string, message: string}>
     * @throws \DateMalformedStringException
     * @throws \DateMalformedIntervalStringException
     */
    private function handleSmsReset(User $user, string $newPassword, string $clientIp, User $byUser): array
    {
        $flashes = [];
        $data = $this->getSettings->getSettings();

        $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
            $user,
            AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_DASHBOARD->value
        );

        $smsResendInterval = (is_array($data) && isset($data[SettingName::SMS_TIMER_RESEND->value]['value']))
            ? $data[SettingName::SMS_TIMER_RESEND->value]['value']
            : 5;

        $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
        $currentTime = new DateTime();

        $latestEventMetadata = $latestEvent instanceof Event ? $latestEvent->getEventMetadata() : [];
        $lastResetTime = isset($latestEventMetadata['lastResetAccountPasswordTime'])
            ? new DateTime($latestEventMetadata['lastResetAccountPasswordTime'])
            : null;
        $resetAttempts = $latestEventMetadata['resetAttempts'] ?? 0;

        $canSend = (!$latestEvent instanceof Event || $resetAttempts < 3)
            && (!$latestEvent instanceof Event || ($lastResetTime instanceof DateTime && $lastResetTime->add(
                $minInterval
            ) < $currentTime));

        if (!$canSend) {
            return $flashes;
        }

        $message = $this->translator->trans('newPasswordMessage', ['%password%' => $newPassword], 'controllers');
        $smsResponse = $this->sendSMS->sendSmsNoValidation($user, $message);

        if ($smsResponse !== '' && $smsResponse !== '0') {
            $flashes[] = [
                'type' => 'success',
                'message' => $this->translator->trans('passwordSentSMS', [], 'controllers')
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_DASHBOARD->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $clientIp,
                    EventMetadataKeysType::PERFORMED_ON_UUID->value => $user->getUuid(),
                    EventMetadataKeysType::UUID->value => $byUser->getUuid(),
                    EventMetadataKeysType::RESET_ATTEMPTS->value => $resetAttempts + 1,
                ]
            );
        } else {
            $flashes[] = [
                'type' => 'error',
                'message' => $this->translator->trans('passwordNotSentSMS', [], 'controllers')
            ];
        }

        return $flashes;
    }
}
