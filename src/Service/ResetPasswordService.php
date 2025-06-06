<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ResetPasswordService
{

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
        private ParameterBagInterface $parameterBag,
        private GetSettings $getSettings,
        private TranslatorInterface $translator,
        private MailerInterface $mailer,
        private SendSMS $sendSMS,
        private EventActions $eventActions,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     */
    public function resetPasswordForLocalAccounts(User $adminUser, string $ip, string $user_agent): void
    {
        $localUsers = $this->userRepository->findAllPortalAccountsExcludingAdmin();
        foreach ($localUsers as $user) {
            $user->setForgotPasswordRequest(true);
            $this->resetPasswordEmail($user);
            $this->entityManager->persist($user);
            $eventMetadata = [
                'ip' => $ip,
                'user_agent' => $user_agent,
                'edited ' => $user->getUuid(),
                'by' => $adminUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI->value,
                new DateTime(),
                $eventMetadata
            );
        }
        $this->entityManager->flush();
    }

    /**
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    private function resetPasswordEmail(User $user): void
    {
        $randomPassword = bin2hex(random_bytes(4));
        $hashedPassword = $this->userPasswordHasher->hashPassword($user, $randomPassword);
        $user->setPassword($hashedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $data = $this->getSettings->getSettings();

        if ($user->getUserExternalAuths()[0]->getProviderId() === UserProvider::EMAIL->value) {
            $emailSender = $this->parameterBag->get('app.email_address');
            $nameSender = $this->parameterBag->get('app.sender_name');
            $supportTeam = $data['PAGE_TITLE']['value'];
            $contactEmail = $data['CONTACT_EMAIL']['value'];
            // Send email
            $email = new TemplatedEmail()
                ->from(new Address($emailSender, $nameSender))
                ->to($user->getEmail())
                ->subject($this->translator->trans('subject_password_reset_details', [], 'user_password_reset'))
                ->htmlTemplate('email/user_password_reset.html.twig')
                ->context([
                    'password' => $randomPassword,
                    'supportTeam' => $supportTeam,
                    'contactEmail' => $contactEmail
                ]);
            $this->mailer->send($email);
        }
        if ($user->getUserExternalAuths()[0]->getProviderId() === UserProvider::PHONE_NUMBER->value) {
            $message = "Your new account password is: " . $randomPassword . "%0A";
            $this->sendSMS->sendSmsNoValidation($user->getPhoneNumber(), $message);
        }
    }
}