<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\OTPcode;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Enum\TwoFAType;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class TwoFAService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SendSMS $sendSMS,
        private MailerInterface $mailer,
        private ParameterBagInterface $parameterBag,
        private SettingRepository $settingRepository,
        private EventActions $eventActions,
        private GetSettings $getSettings,
        private EventRepository $eventRepository,
    ) {
    }

    public function validate2FACode(User $user, string $formCode): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $codeDate = $user->getTwoFACodeGeneratedAt();
        // If the user doesn't have code in the BD return false
        if (!$codeDate instanceof DateTimeInterface) {
            return false;
        }
        $now = new DateTime();
        $diff = $now->getTimestamp() - $codeDate->getTimestamp();
        $timeToExpireCode = $data["TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME"]["value"];
        if ($diff >= $timeToExpireCode) {
            return false;
        }
        if ($user->getTwoFACode() === $formCode) {
            $user->setTwoFAcodeIsActive(false);
            return true;
        }
        return false;
    }

    /**
     * @throws RandomException
     */
    private function twoFACode(User $user): int
    {
        // Generate a random verification code with 7 digits
        $verificationCode = random_int(1000000, 9999999);
        $user->setTwoFACode($verificationCode);
        $user->setTwoFACodeGeneratedAt(new DateTime());
        $user->setTwoFAcodeIsActive(true);
        $this->userRepository->save($user, true);

        return $verificationCode;
    }

    public function generate2FACode(User $user, string $ip, string $userAgent, string $eventType): ?string
    {
        // Generate code
        $code = $this->twoFACode($user);
        // Send code
        $this->sendCode($user, $code, $ip, $userAgent, $eventType);
        return $user->getTwoFAcode();
    }

    public function resendCode(User $user, ?string $ip, ?string $userAgent, string $eventType): void
    {
        $code = $this->twoFACode($user);
        $this->sendCode($user, $code, $ip, $userAgent, $eventType);
    }

    /**
     * @throws RandomException
     */
    public function generateOTPCodes(User $user): void
    {
        // If the user already has codes, they must be removed before generating new ones.
        if ($user->getOTPcodes()) {
            foreach ($user->getOTPcodes() as $code) {
                $this->entityManager->remove($code);
            }
        }
        $nCodes = 12; // Number of codes generated.
        $createdCodes = 0;
        while ($createdCodes < $nCodes) {
            $code = $this->generateMixedCode();
            $otpCode = new OTPcode();
            $otpCode->setCode($code);
            $otpCode->setUser($user);
            $otpCode->setActive(false);
            $otpCode->setCreatedAt(new DateTime());
            $user->addOTPcode($otpCode);
            $this->entityManager->persist($otpCode);
            $this->entityManager->persist($user);
            $createdCodes++;
        }
        $this->entityManager->flush();
    }

    public function validateOTPCodes(User $user, string $formCode): bool
    {
        $twoFACodes = $user->getOTPcodes();
        foreach ($twoFACodes as $code) {
            // Verify if the code exists and if this code is valid
            if ($code->getCode() === $formCode && $code->isActive()) {
                // As we can only use the code once, we have to deactivate it after it is used.
                $code->setActive(false);
                $this->entityManager->persist($code);
                $this->entityManager->flush();
                return true;
            }
        }
        return false;
    }

    /**
     * @throws RandomException
     */
    private function generateMixedCode(): string
    {
        // Generate random alphanumeric codes
        $bytes = random_bytes(8 / 2);
        $hexCode = bin2hex($bytes);

        $alphanumericCode = str_replace(['a', 'b', 'c', 'd', 'e', 'f'], ['X', 'Y', 'Z', 'P', 'Q', 'R'], $hexCode);

        return strtoupper(substr($alphanumericCode, 0, 8));
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function sendCode(User $user, string $code, ?string $ip, ?string $userAgent, string $eventType): void
    {
        $messageType = $user->getTwoFAtype();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $secondsLeft = $data["TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME"]["value"];
        if ($messageType === UserTwoFactorAuthenticationStatus::EMAIL->value || $user->getEmail()) {
            $emailTitle = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
            // Send email to the user with the verification code
            $email = new TemplatedEmail()
                ->from(
                    new Address(
                        $this->parameterBag->get('app.email_address'),
                        $this->parameterBag->get('app.sender_name')
                    )
                )
                ->to($user->getEmail())
                ->subject('Your OpenRoaming Two Factor Authentication code is ' . $code . '.')
                ->htmlTemplate('email/user_code.html.twig')
                ->context([
                    'uuid' => $user->getEmail(),
                    'emailTitle' => $emailTitle,
                    'verificationCode' => $code,
                    'is2FATemplate' => true,
                    'secondsLeft' => $secondsLeft,
                ]);

            $this->mailer->send($email);
        } elseif ($messageType === UserTwoFactorAuthenticationStatus::SMS->value || $user->getPhoneNumber()) {
            $message = "Your Two Factor Authentication Code is " . $code;
            $this->sendSMS->sendSms($user->getPhoneNumber(), $message);
        }
        $eventMetaData = [
            'platform' => PlatformMode::LIVE->value,
            'user_agent' => $userAgent ?? null,
            'uuid' => $user->getUuid(),
            'ip' => $ip ?? null,
        ];
        $this->eventActions->saveEvent(
            $user,
            $eventType,
            new DateTime(),
            $eventMetaData
        );
    }

    public function twoFAisActive(User $user): bool
    {
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value) {
            return false;
        }
        return $this->hasValidOTPCodes($user);
    }

    public function saveCodes(User $user): void
    {
        foreach ($user->getOTPcodes() as $code) {
            $code->setActive(true);
            $this->entityManager->persist($code);
        }
        $this->entityManager->flush();
    }

    public function canResendCode(User $user): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $nrAttempts = $data["TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE"]["value"];
        $timeToResetAttempts = $data["TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS"]["value"];
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeToResetAttempts . ' minutes');
        $attempts = $this->eventRepository->find2FACodeAttemptEvent(
            $user,
            $nrAttempts,
            $limitTime,
            AnalyticalEventType::TWO_FA_CODE_RESEND->value
        );
        return count($attempts) < $nrAttempts;
    }

    public function timeIntervalToResendCode(User $user): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $timeIntervalToResendCode = $data["TWO_FACTOR_AUTH_RESEND_INTERVAL"]["value"];
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeIntervalToResendCode . ' seconds');
        $attempts = $this->eventRepository->find2FACodeAttemptEvent(
            $user,
            1,
            $limitTime,
            AnalyticalEventType::TWO_FA_CODE_RESEND->value
        );
        return count($attempts) < 1;
    }

    public function timeIntervalToSendCode(User $user, string $event): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $timeIntervalToResendCode = $data["TWO_FACTOR_AUTH_RESEND_INTERVAL"]["value"];
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeIntervalToResendCode . ' seconds');
        $attempts = $this->eventRepository->find2FACodeAttemptEvent(
            $user,
            1,
            $limitTime,
            $event
        );
        return count($attempts) < 1;
    }

    private function removeOTPCodes(User $user): void
    {
        $codes = $user->getOTPcodes();
        foreach ($codes as $code) {
            $user->removeOTPcode($code);
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();
    }

    public function disable2FA(User $user): void
    {
        $this->removeOTPCodes($user);
        $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::DISABLED->value);
        $user->setTwoFAsecret(null);
        $user->setTwoFAcode(null);
        $user->setTwoFAcodeGeneratedAt(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function event2FA(string $ip, User $user, string $eventType, string $userAgent): void
    {
        $eventMetaData = [
            'platform' => PlatformMode::LIVE->value,
            'user_agent' => $userAgent,
            'uuid' => $user->getUuid(),
            'ip' => $ip,
        ];
        $this->eventActions->saveEvent(
            $user,
            $eventType,
            new DateTime(),
            $eventMetaData
        );
    }

    public function canValidationCode(User $user, string $eventType): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $timeToResetAttempts = $data["TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS"]["value"];
        $nrAttempts = $data["TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE"]["value"];
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeToResetAttempts . ' minutes');
        $attempts = $this->eventRepository->find2FACodeAttemptEvent($user, $nrAttempts, $limitTime, $eventType);
        return count($attempts) < $nrAttempts;
    }

    public function hasValidOTPCodes(User $user): bool
    {
        $codes = $user->getOTPcodes();
        foreach ($codes as $code) {
            if ($code->isActive()) {
                return true;
            }
        }
        return false;
    }

    public function timeLeftToResendCode(User $user, string $eventType): int
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $timeToResetAttempts = $data["TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS"]["value"];
        $lastEvent = $this->eventRepository->findLatest2FACodeAttemptEvent(
            $user,
            $eventType
        );
        $lastAttemptTime = $lastEvent instanceof Event ?
            $lastEvent->getEventDatetime() : $timeToResetAttempts;
        $now = new DateTime();
        $lastAttemptTime->modify('+' . $timeToResetAttempts . ' minutes');
        $interval = date_diff($now, $lastAttemptTime);
        $interval_minutes = $interval->days * 1440;
        $interval_minutes += $interval->h * 60;
        return $interval_minutes + $interval->i;
    }

    public function timeLeftToResendCodeTimeInterval(User $user, string $eventType): int
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $timeToResetAttempts = $data["TWO_FACTOR_AUTH_RESEND_INTERVAL"]["value"];
        $lastEvent = $this->eventRepository->findLatest2FACodeAttemptEvent(
            $user,
            $eventType
        );
        $lastAttemptTime = $lastEvent instanceof Event ?
            $lastEvent->getEventDatetime() : $timeToResetAttempts;
        $now = new DateTime();
        $lastAttemptTime->modify('+' . $timeToResetAttempts . ' seconds');
        $interval = date_diff($now, $lastAttemptTime);
        $interval_seconds = $interval->days * 1440;
        $interval_seconds += $interval->h * 60;
        $interval_seconds += $interval->i;
        return $interval_seconds + $interval->s;
    }

    public function userNeedTwoFA(User $user): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $twoFAPlatformStatus = $data["TWO_FACTOR_AUTH_STATUS"]["value"];
        $userTwoFAPlatformStatus = $user->getTwoFAtype();
        if ($twoFAPlatformStatus === TwoFAType::ENFORCED_FOR_LOCAL->value) {
            if (
                $user->getUserExternalAuths()[0] &&
                $user->getUserExternalAuths()[0]->getProvider() === UserProvider::PORTAL_ACCOUNT->value
            ) {
                return $userTwoFAPlatformStatus === UserTwoFactorAuthenticationStatus::DISABLED->value;
            }
            return false;
        }
        if ($twoFAPlatformStatus === TwoFAType::ENFORCED_FOR_ALL->value) {
            return $userTwoFAPlatformStatus === UserTwoFactorAuthenticationStatus::DISABLED->value;
        }
        return false;
    }
}
