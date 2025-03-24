<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\SettingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class RegistrationEmailGenerator
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private MailerInterface $mailer,
        private SettingRepository $settingRepository,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendRegistrationEmail(User $user, $password): void
    {
        $supportTeam = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();

        // Send email to the user with the verification code
        $email = new TemplatedEmail()
            ->from(
                new Address(
                    $this->parameterBag->get('app.email_address'),
                    $this->parameterBag->get('app.sender_name')
                )
            )
            ->to($user->getEmail())
            ->subject('Your OpenRoaming Registration Details')
            ->htmlTemplate('email/user_password.html.twig')
            ->context([
                'uuid' => $user->getEmail(),
                'supportTeam' => $supportTeam,
                'verificationCode' => $user->getVerificationCode(),
                'isNewUser' => true,
                // This variable informs if the user it's new our if it's just a password reset request
                'password' => $password,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendNotifyExpiresProfileEmail(User $user, int $timeLeft): void
    {
        $supportTeam = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $contactEmail = $this->settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();

        // Send email to the user with the verification code
        $email = new TemplatedEmail()
            ->from(
                new Address(
                    $this->parameterBag->get('app.email_address'),
                    $this->parameterBag->get('app.sender_name')
                )
            )
            ->to($user->getEmail())
            ->subject('Your OpenRoaming Profile is about to expire')
            ->htmlTemplate('email/expiresProfile.html.twig')
            ->context([
                'uuid' => $user->getEmail(),
                'supportTeam' => $supportTeam,
                'contactEmail' => $contactEmail,
                'timeLeft' => $timeLeft,
            ]);
        $this->mailer->send($email);
    }

    public function sendNotifyExpiredProfile(User $user): void
    {
        $supportTeam = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
        $contactEmail = $this->settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue();

        // Send email to the user with the verification code
        $email = new TemplatedEmail()
            ->from(
                new Address(
                    $this->parameterBag->get('app.email_address'),
                    $this->parameterBag->get('app.sender_name')
                )
            )
            ->to($user->getEmail())
            ->subject('Your OpenRoaming Profile is about to expire')
            ->htmlTemplate('email/expiredProfile.html.twig')
            ->context([
                'uuid' => $user->getEmail(),
                'contactEmail' => $contactEmail,
                'supportTeam' => $supportTeam,
            ]);

        $this->mailer->send($email);
    }
}
