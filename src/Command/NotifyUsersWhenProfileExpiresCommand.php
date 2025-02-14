<?php

namespace App\Command;

use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\ExpirationProfileService;
use App\Service\ProfileManager;
use App\Service\RegistrationEmailGenerator;
use App\Service\SendSMS;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'notify:usersWhenProfileExpires',
    description: 'Notify users when profile is about to expire based on the provider it has used',
)]
class NotifyUsersWhenProfileExpiresCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SendSMS $sendSMS,
        private readonly ProfileManager $profileManager,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly UserRadiusProfileRepository $userRadiusProfileRepository,
        private readonly RegistrationEmailGenerator $registrationEmailGenerator,
        private readonly ExpirationProfileService $expirationProfileService,
        private readonly SettingRepository $settingRepository,
        private readonly NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws NonUniqueResultException
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function notifyUsersWhenProfileExpires(OutputInterface $output): void
    {
        $userRadiusProfiles = $this->userRadiusProfileRepository->findAll();
        $notificationResendInterval = $this->settingRepository->findOneBy(['name' => 'TIME_INTERVAL_NOTIFICATION']);

        foreach ($userRadiusProfiles as $userRadiusProfile) {
            $user = $userRadiusProfile->getUser();
            if (!$user || $user->getDeletedAt() !== null) {
                continue; // Skip profiles without associated or deleted users
            }

            $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);
            if (!$userExternalAuth) {
                $output->writeln("No authentication method found for user ID: " . $user->getId());
                continue;
            }

            // Use the service to calculate expiration and alert times
            $expirationData = $this->expirationProfileService->calculateExpiration(
                $userExternalAuth->getProvider(),
                $userExternalAuth->getProviderId(),
                $userRadiusProfile,
                'signing-keys/cert.pem'
            );

            $limitTime = $expirationData['limitTime'];
            $alertTime = $expirationData['notifyTime'];
            $realTime = new DateTime();

            $timeLeft = $limitTime->diff($realTime);
            $timeLeftDays = $timeLeft->invert === 0 ? $timeLeft->days + 1 : 0;

            $lastNotification = $this->notificationRepository->findLastNotificationByType(
                $user,
                NotificationType::PROFILE_EXPIRATION->value
            );

            $sendNotification = true;

            if ($notificationResendInterval && $lastNotification) {
                $dateToResend = new DateTime(
                    $lastNotification->getLastNotification()->format('Y-m-d H:i:s')
                )->modify('+' . $notificationResendInterval->getValue() . ' days');

                if ($realTime < $dateToResend) {
                    $sendNotification = false;
                }
            }
            if (
                $sendNotification &&
                $realTime >= $alertTime &&
                $realTime <= $limitTime &&
                $userRadiusProfile->getStatus() === 1
            ) {
                $notification = new Notification();
                $notification->setType(NotificationType::PROFILE_EXPIRATION->value);
                $notification->setUser($user);
                $notification->setLastNotification($realTime);

                try {
                    // Priority: Send Email Notification
                    if ($user->getEmail()) { // For Google/Portal/Microsoft future accounts - any account with an email
                        $this->registrationEmailGenerator->sendNotifyExpiresProfileEmail(
                            $user,
                            $timeLeftDays + 1
                        );
                        $output->writeln("Email sent to user ID {$user->getId()}");
                        try {
                            $this->entityManager->persist($notification);
                            $this->entityManager->flush();
                        } catch (Exception $e) {
                            $output->writeln(
                                "Failed to store notification for user ID {$user->getId()}: " . $e->getMessage()
                            );
                        }
                        break; // Stop processing once the match is found
                    }

                    $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
                    foreach ($userExternalAuths as $externalAuth) {
                        // If email is not sent, try to use phoneNumber
                        if (
                            ($externalAuth->getProvider() === UserProvider::PORTAL_ACCOUNT->value) &&
                            $user->getPhoneNumber() &&
                            $externalAuth->getProviderId() === UserProvider::PHONE_NUMBER->value
                        ) {
                            $this->sendSMS->sendSms(
                                $user->getPhoneNumber(),
                                'Your OpenRoaming profile will expire in ' . ($timeLeftDays + 1) . ' days.'
                            );
                            $output->writeln("SMS sent to user ID {$user->getId()}");
                            try {
                                $this->entityManager->persist($notification);
                                $this->entityManager->flush();
                            } catch (Exception $e) {
                                $output->writeln(
                                    "Failed to store notification for user ID {$user->getId()}: " . $e->getMessage()
                                );
                            }
                            break; // Stop processing once the match is found
                        }
                    }
                } catch (Exception $e) {
                    $output->writeln("Failed to send notification for user ID {$user->getId()}: " . $e->getMessage());
                }
            }

            if (
                $realTime > $limitTime &&
                $userRadiusProfile->getStatus() === 1
            ) {
                $this->profileManager->disableProfiles(
                    $user,
                    UserRadiusProfileRevokeReason::PROFILE_EXPIRED->value,
                    true
                );
                $this->registrationEmailGenerator->sendNotifyExpiredProfile($user);
                $this->entityManager->persist($userRadiusProfile);
                $this->entityManager->flush();
            }
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws NonUniqueResultException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notifyUsersWhenProfileExpires($output);
        $output->writeln('Users notified');

        return Command::SUCCESS;
    }
}
