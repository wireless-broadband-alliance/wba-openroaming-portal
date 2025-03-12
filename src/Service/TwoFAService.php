<?php

namespace App\Service;

use App\Entity\OTPcode;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTime;
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

class TwoFAService
{
    /**
     * Registration constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     * @param EventRepository $eventRepository The entity returns the last events data related to each user.
     * @param SendSMS $sendSMS Calls the sendSMS service
     * @param MailerInterface $mailer Called for send emails
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly SendSMS $sendSMS,
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
        private readonly SettingRepository $settingRepository,
        private readonly EventActions $eventActions,
        private readonly GetSettings $getSettings,
        private readonly EventRepository $eventRepository,
    ) {
    }
    public function validate2FACode(User $user, string $formCode): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $codeDate = $user->getTwoFACodeGeneratedAt();
        // if the user don't have code in the BD return false
        if (!$codeDate instanceof \DateTimeInterface) {
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

    /**
     * @throws RandomException
     */
    public function generate2FACode(User $user): ?string
    {
        // Generate code
        $code = $this->twoFACode($user);
        // Send code
        $this->sendCode($user, $code);
        return $user->getTwoFAcode();
    }

    /**
     * @throws RandomException
     */
    public function resendCode(User $user): void
    {
        $code = $this->twoFACode($user);
        $this->sendCode($user, $code);
    }

    public function generateOTPCodes(User $user): array
    {
        // If the user already have code we need to remove that codes before generate new ones.
        if ($user->getOTPcodes()) {
            foreach ($user->getOTPcodes() as $code) {
                $this->entityManager->remove($code);
            }
        }
        $codes = [];
        $nCodes = 12; // Number of codes generated.
        $createdCodes = 0;
        while ($createdCodes < $nCodes) {
            $code = $this->generateMixedCode();
            $codes[] = $code;
            $createdCodes++;
        }
        return $codes;
    }

    public function validateOTPCodes(User $user, string $formCode): bool
    {
        // Get the OTP codes from user
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

    private function sendCode(User $user, string $code): void
    {

        $messageType = $user->getTwoFAtype();
        if ($messageType === UserTwoFactorAuthenticationStatus::SMS->value || $user->getPhoneNumber()) {
            $message = "Your Two Factor Authentication Code is " . $code;
            $this->sendSMS->sendSms($user->getPhoneNumber(), $message);
        }
        if ($messageType === UserTwoFactorAuthenticationStatus::EMAIL->value || $user->getEmail()) {
            // Send email to the user with the verification code
            $email = new TemplatedEmail()
                ->from(
                    new Address(
                        $this->parameterBag->get('app.email_address'),
                        $this->parameterBag->get('app.sender_name')
                    )
                )
                ->to($user->getEmail())
                ->subject('Your OpenRoaming Two Factor Authentication code')
                ->htmlTemplate('email/user_code.html.twig')
                ->context([
                    'uuid' => $user->getEmail(),
                    'verificationCode' => $code,
                ]);

            $this->mailer->send($email);
        }
        $eventMetaData = [
            'platform' => PlatformMode::LIVE->value,
            'uuid' => $user->getUuid(),
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::TWO_FA_CODE_SENT->value,
            new DateTime(),
            $eventMetaData
        );
    }

    public function twoFAisActive(User $user): bool
    {
        if ($user->getTwoFAtype() === UserTwoFactorAuthenticationStatus::DISABLED->value) {
                return false;
        }
        return !$user->getOTPcodes()->isEmpty();
    }

    public function saveCodes(mixed $codes, User $user): void
    {
        foreach ($codes as $code) {
            $otp = new OTPcode();
            $otp->setUser($user);
            $otp->setCode($code);
            $otp->setActive(true);
            $otp->setCreatedAt(new DateTime());
            $user->addOTPcode($otp);
            $this->entityManager->persist($otp);
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
        $attempts = $this->eventRepository->find2FACodeAttemptEvent($user, $nrAttempts, $limitTime);
        return count($attempts) < $nrAttempts;
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
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function event2FA(string $ip, User $user, string $eventType): void
    {
        $eventMetaData = [
            'platform' => PlatformMode::LIVE->value,
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
}
