<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Exception;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class VerificationCodeEmailGenerator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly SendSMS $sendSMS
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
        $user->setVerificationCodecreatedAt(new \DateTime());
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
        $codeDate = $user->getVerificationCodecreatedAt();
        if (!$codeDate) {
            return false;
        }
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $codeDate->getTimestamp();
        if ($diff >= 30) {
            return false;
        }
        return $user->getVerificationCode() === $formCode;
    }

    public function generate2FACode(User $user): int
    {
        // Generate a random verification code with 6 digits
        $codeDate = $user->getVerificationCodecreatedAt();
        if (!$codeDate) {
            $code = $this->generateVerificationCode($user);
            $this->sendSMS->sendSms($user->getPhoneNumber(), "Your OpenRoaming security code is: " . $code);
            return $code;

        }
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $codeDate->getTimestamp();
        if ($diff >= 30) {
            $code = $this->generateVerificationCode($user);
            $this->sendSMS->sendSms($user->getPhoneNumber(), "Your OpenRoaming security code is: " . $code);
            return $code;
        }
        return $user->getVerificationCode();
    }
}
