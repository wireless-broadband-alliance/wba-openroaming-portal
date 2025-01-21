<?php

namespace App\Command;

use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\ExpirationProfileService;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
use App\Service\RegistrationEmailGenerator;
use App\Service\SendSMS;
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
    private EntityManagerInterface $entityManager;
    public ProfileManager $profileManager;
    public PgpEncryptionService $pgpEncryptionService;
    public SendSMS $sendSMS;
    public RegistrationEmailGenerator $registrationEmailGenerator;
    private UserExternalAuthRepository $userExternalAuthRepository;
    private UserRadiusProfileRepository $userRadiusProfileRepository;
    private ExpirationProfileService $expirationProfileService;
    private SettingRepository $settingRepository;
    private NotificationRepository $notificationRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        PgpEncryptionService $pgpEncryptionService,
        SendSMS $sendSMS,
        ProfileManager $profileManager,
        UserExternalAuthRepository $userExternalAuthRepository,
        UserRadiusProfileRepository $userRadiusProfileRepository,
        RegistrationEmailGenerator $registrationEmailGenerator,
        ExpirationProfileService $expirationProfileService,
        SettingRepository $settingRepository,
        NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->pgpEncryptionService = $pgpEncryptionService;
        $this->sendSMS = $sendSMS;
        $this->profileManager = $profileManager;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->userRadiusProfileRepository = $userRadiusProfileRepository;
        $this->registrationEmailGenerator = $registrationEmailGenerator;
        $this->expirationProfileService = $expirationProfileService;
        $this->settingRepository = $settingRepository;
        $this->notificationRepository = $notificationRepository;
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
            $realTime = new \DateTime();

            $timeLeft = $limitTime->diff($realTime);
            $timeLeftDays = $timeLeft->invert === 0 ? $timeLeft->days + 1 : 0;


            $timeToResendNot = $this->settingRepository->findOneBy(['name' => 'TIME_INTERVAL_NOTIFICATION']);
            $lastNotification = $this->notificationRepository->findLastNotificationByType(
                $user,
                NotificationType::PROFILE_EXPIRATION
            );

            if ($timeToResendNot and $lastNotification) {
                $dateToResend = $lastNotification
                    ->getLastNotification()
                    ->modify('+' . $timeToResendNot->getValue() . ' days');
                $interval = $dateToResend->diff($realTime);
                if ($interval->days > 0) {
                    $timeToResendFlag = true;
                } else {
                    $timeToResendFlag = false;
                }
            } elseif ($timeToResendNot and !$lastNotification) {
                $timeToResendFlag = true;
            } else {
                $timeToResendFlag = false;
            }
            // Notify user if within alert window
            if (
                $realTime >= $alertTime &&
                $realTime <= $limitTime &&
                $userRadiusProfile->getStatus() === 1 &&
                $timeToResendFlag
            ) {;
                $notification = new Notification();
                $notification->setType(NotificationType::PROFILE_EXPIRATION);
                $notification->setUser($user);
                $notification->setLastNotification($realTime);
                $this->entityManager->persist($notification);
                $this->entityManager->flush();

                if ($user->getEmail()) {
                    $this->registrationEmailGenerator->sendNotifyExpiresProfileEmail($user, $timeLeftDays + 1);
                }
                if ($user->getPhoneNumber()) {
                    $this->sendSMS->sendSms(
                        $user->getPhoneNumber(),
                        'Your OpenRoaming profile will expire in ' . ($timeLeftDays + 1) . ' days.'
                    );
                }
            }

            // Disable profile if expired
            if (
                $realTime > $limitTime &&
                $userRadiusProfile->getStatus() === 1
            ) {
                $this->disableProfiles($user);
                $this->registrationEmailGenerator->sendNotifyExpiredProfile($user);
                $this->entityManager->persist($userRadiusProfile);
                $this->entityManager->flush();
            }
        }
    }

    private function disableProfiles($user): void
    {
        $this->profileManager->disableProfiles($user, true);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
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
