<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

readonly class VerificationCodeEmailGenerator
{
    public function __construct(
        private UserRepository $userRepository,
        private SettingRepository $settingRepository,
        private ParameterBagInterface $parameterBag,
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
        $user->setVerificationCode((string)$verificationCode);
        $user->setCreatedAt(new \DateTime());
        $this->userRepository->save($user, true);

        return $verificationCode;
    }

    /**
     * Create an email message with the verification code.
     *
     * @return Email The email with the code.
     * @throws Exception
     */
    public function createEmailAdminPage(
        User $user,
    ): Email {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        $verificationCode = $this->generateVerificationCode($user);

        return new TemplatedEmail()
            ->from(new Address($emailSender, $nameSender))
            ->to($user->getEmail())
            ->subject('Your Settings Reset Details')
            ->htmlTemplate('email/admin_reset.html.twig')
            ->context([
                'verificationCode' => $verificationCode,
                'resetPassword' => false
            ]);
    }

    /**
     * Create an email message with the verification code.
     *
     * @return Email The email with the code.
     * @throws Exception
     */
    public function createEmailLanding(User $user): Email
    {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        // If the verification code is not provided, generate a new one
        $verificationCode = $this->generateVerificationCode($user);
        $emailTitle = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();

        return new TemplatedEmail()
            ->from(new Address($emailSender, $nameSender))
            ->to($user->getEmail())
            ->subject('Your OpenRoaming Authentication Code is: ' . $verificationCode)
            ->htmlTemplate('email/user_code.html.twig')
            ->context([
                'verificationCode' => $verificationCode,
                'uuid' => $user->getEmail(),
                'emailTitle' => $emailTitle,
                'is2FATemplate' => false,
            ]);
    }

    /**
     * Create an email message about 2fa disabled by the admin.
     *
     * @return Email The email with the code.
     * @throws Exception
     */
    public function createEmail2FADisabledBy(User $user): Email
    {
        // Get the values from the services.yaml file using $parameterBag on the __construct
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        // If the verification code is not provided, generate a new one
        $supportTeam = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $contactEmail = $this->settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();

        return new TemplatedEmail()
            ->from(new Address($emailSender, $nameSender))
            ->to($user->getEmail())
            ->subject('Your OpenRoaming Two-Factor Authentication has been disabled')
            ->htmlTemplate('email/admin_disabled_2fa.html.twig')
            ->context([
                'uuid' => $user->getEmail(),
                'supportTeam' => $supportTeam,
                'contactEmail' => $contactEmail
            ]);
    }
}
