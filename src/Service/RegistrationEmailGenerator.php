<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class RegistrationEmailGenerator
{
    private ParameterBagInterface $parameterBag;
    private MailerInterface $mailer;

    /**
     * @param ParameterBagInterface $parameterBag
     * @param MailerInterface $mailer
     */
    public function __construct(
        ParameterBagInterface $parameterBag,
        MailerInterface $mailer,
    ) {
        $this->parameterBag = $parameterBag;
        $this->mailer = $mailer;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendRegistrationEmail($user, $randomPassword): void
    {
        $emailSender = $this->parameterBag->get('app.email_address');
        $nameSender = $this->parameterBag->get('app.sender_name');

        // Send email to the user with the verification code
        $email = (new TemplatedEmail())
            ->from(new Address($emailSender, $nameSender))
            ->to($user->getEmail())
            ->subject('Your OpenRoaming Registration Details')
            ->htmlTemplate('email/user_password.html.twig')
            ->context([
                'uuid' => $user->getUuid(),
                'verificationCode' => $user->getVerificationCode(),
                'isNewUser' => true,
                // This variable informs if the user it's new our if it's just a password reset request
                'password' => $randomPassword,
            ]);

        $this->mailer->send($email);
    }
}
