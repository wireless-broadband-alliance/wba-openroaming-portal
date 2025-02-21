<?php

namespace App\Service;

use App\Entity\OTPcode;
use App\Entity\TwoFactorAuthentication;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class TwoFAService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }
    public function validate2FACode(User $user, string $formCode): bool
    {
        $codeDate = $user->getTwoFactorAuthentication() instanceof TwoFactorAuthentication ?
            $user->getTwoFactorAuthentication()->getCodeGeneratedAt() : null;
        // if the user don't have code in the BD return false
        if (!$codeDate instanceof \DateTimeInterface) {
            return false;
        }
        $now = new DateTime();
        // Difference between created time and now
        $diff = $now->getTimestamp() - $codeDate->getTimestamp();
        // If 30 seconds have passed since the code was created return false.
        if ($diff >= 30) {
            return false;
        }
        return $user->getTwoFactorAuthentication()->getCode() === $formCode;
    }

    public function twoFACode(User $user): int
    {
        // Generate a random verification code with 7 digits
        $verificationCode = random_int(1000000, 9999999);
        if ($user->getTwoFactorAuthentication() instanceof TwoFactorAuthentication) {
            $user->getTwoFactorAuthentication()->setCode($verificationCode);
            $user->getTwoFactorAuthentication()->setCodeGeneratedAt(new DateTime());
        }
        $this->userRepository->save($user, true);

        return $verificationCode;
    }

    public function generate2FACode(User $user)
    {
        $codeDate = $user->getTwoFactorAuthentication() instanceof TwoFactorAuthentication ?
            $user->getTwoFactorAuthentication()->getCodeGeneratedAt() : null;
        // If the user don't have an older code return a new one
        if (!$codeDate instanceof \DateTimeInterface) {
            return $this->twoFACode($user);
        }
        $now = new DateTime();
        $diff = $now->getTimestamp() - $codeDate->getTimestamp();
        // Only create a new code if the oldest one was expired
        if ($diff >= 30) {
            return $this->twoFACode($user);
        }
        return $user->getVerificationCode();
    }

    public function generateOTPcodes(User $user): void
    {
        $twoFA = $user->getTwoFactorAuthentication();
        // if the user already have code we need to remove that codes before generate new ones.
        if ($twoFA && $twoFA->getOTPcodes()) {
            foreach ($twoFA->getOTPcodes() as $code) {
                $this->entityManager->remove($code);
            }
        }
        $nCodes = 12; // Number of codes generated.
        $createdCodes = 0;
        while ($createdCodes < $nCodes) {
            $code = $this->generateMixedCode();
            $otp = new OTPcode();
            $otp->setTwoFactorAuthentication($twoFA);
            $otp->setCode($code);
            $otp->setActive(true);
            $otp->setCreatedAt(new DateTime());
            $twoFA->addOTPcode($otp);
            $this->entityManager->persist($otp);
            $createdCodes++;
        }
        $this->entityManager->persist($twoFA);
        $this->entityManager->flush();
    }

    public function validateOTPCodes(User $user, string $formCode): bool
    {
        // Get the OTP codes from user
        $twoFAcodes = $user->getTwoFactorAuthentication()->getOTPcodes();
        foreach ($twoFAcodes as $code) {
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

    private function generateMixedCode(int $length = 8): string
    {
        // Generate random alphanumeric codes
        $bytes = random_bytes($length / 2);
        $hexCode = bin2hex($bytes);

        $alphanumericCode = str_replace(['a', 'b', 'c', 'd', 'e', 'f'], ['X', 'Y', 'Z', 'P', 'Q', 'R'], $hexCode);

        return strtoupper(substr($alphanumericCode, 0, $length));
    }

}