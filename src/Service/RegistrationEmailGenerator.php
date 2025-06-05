<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\SettingRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class RegistrationEmailGenerator
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private MailerInterface $mailer,
        private SettingRepository $settingRepository,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendRegistrationEmail(User $user, $password): void
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
            ->subject($this->translator->trans('subject_registration_details', [], 'user_registration'))
            ->htmlTemplate('email/user_registration.html.twig')
            ->context([
                'uuid' => $user->getEmail(),
                'supportTeam' => $supportTeam,
                'contactEmail' => $contactEmail,
                'verificationCode' => $user->getTwoFAcode(),
                'password' => $password,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendNotifyExpiresProfileEmail(User $user, int $timeLeft): void
    {
        $emailTitle = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
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
            ->subject($this->translator->trans('subject', [], 'expirationProfiles'))
            ->htmlTemplate('email/expiresProfile.html.twig')
            ->context([
                'uuid' => $user->getEmail(),
                'emailTitle' => $emailTitle,
                'contactEmail' => $contactEmail,
                'timeLeft' => $timeLeft,
            ]);
        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendNotifyExpiredProfile(User $user): void
    {
        $emailTitle = $this->settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue();
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
            ->subject($this->translator->trans('subject_is_expired', [], 'expirationProfiles'))
            ->htmlTemplate('email/expiredProfile.html.twig')
            ->context([
                'uuid' => $user->getEmail(),
                'contactEmail' => $contactEmail,
                'emailTitle' => $emailTitle,
            ]);

        $this->mailer->send($email);
    }
}
