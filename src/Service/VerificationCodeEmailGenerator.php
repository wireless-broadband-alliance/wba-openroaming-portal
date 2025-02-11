<?php

namespace App\Service;

use App\Entity\OTPcode;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class VerificationCodeEmailGenerator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Generate a new verification code for the user.
     *
     * @param User $user The user for whom the verification code is generated.
     * @return int The generated verification code.
     * @throws Exception
     */
    public function generateVerificationCode(User $user): int
    {
        // Generate a random verification code with 6 digits
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode($verificationCode);
        $this->userRepository->save($user, true);

        return $verificationCode;
    }

    /**
     * Create an email message with the verification code.
     *
     * @param string $email The recipient's email address.
     * @return Email The email with the code.
     * @throws Exception
     */
    public function createEmailAdmin(
        string $email,
        User $user,
    ): Email {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        $verificationCode = $this->generateVerificationCode($user);

        return (new TemplatedEmail())
            ->from(new Address($emailSender, $nameSender))
            ->to($email)
            ->subject('Your Settings Reset Details')
            ->htmlTemplate('email/admin_reset.html.twig')
            ->context([
                'verificationCode' => $verificationCode,
                'resetPassword' => false
            ]);
    }

    public function validateCode(User $user, string $formCode): Bool
    {
        if ($user->getTwoFactorAuthentication()) {
            $codeDate = $user->getTwoFactorAuthentication()->getCodeGeneratedAt();
        } else {
            $codeDate = null;
        }
        if (!$codeDate) {
            return false;
        }
        $now = new DateTime();
        $diff = $now->getTimestamp() - $codeDate->getTimestamp();
        if ($diff >= 30) {
            return false;
        }
        return $user->getTwoFactorAuthentication()->getCode() === $formCode;
    }

    public function twoFACode(User $user): int
    {
        // Generate a random verification code with 7 digits
        $verificationCode = random_int(1000000, 9999999);
        if ($user->getTwoFactorAuthentication()) {
            $user->getTwoFactorAuthentication()->setCode($verificationCode);
            $user->getTwoFactorAuthentication()->setCodeGeneratedAt(new DateTime());
        }
        $this->userRepository->save($user, true);

        return $verificationCode;
    }

    public function generate2FACode(User $user): int
    {
        if ($user->getTwoFactorAuthentication()) {
            $codeDate = $user->getTwoFactorAuthentication()->getCodeGeneratedAt();
        } else {
            $codeDate = null;
        }
        if (!$codeDate) {
            return $this->twoFACode($user);
        }
        $now = new DateTime();
        $diff = $now->getTimestamp() - $codeDate->getTimestamp();
        if ($diff >= 30) {
            return $this->twoFACode($user);
        }
        return $user->getVerificationCode();
    }

    public function generateOTPcodes(User $user): void
    {
        $twoFA = $user->getTwoFactorAuthentication();
        if ($twoFA && $twoFA->getOTPcodes()) {
            foreach ($twoFA->getOTPcodes() as $code) {
                $this->entityManager->remove($code);
            }
        }
        $nCodes = 6;
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

    public function validateOTPCodes(User $user, string $formCode): Bool
    {
        $twoFAcodes = $user->getTwoFactorAuthentication()->getOTPcodes();
        foreach ($twoFAcodes as $code) {
            if ($code->getCode() === $formCode && $code->isActive()) {
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
        $bytes = random_bytes($length / 2);
        $hexCode = bin2hex($bytes);

        $alphanumericCode = str_replace(['a', 'b', 'c', 'd', 'e', 'f'], ['X', 'Y', 'Z', 'P', 'Q', 'R'], $hexCode);

        return strtoupper(substr($alphanumericCode, 0, $length));
    }
}
