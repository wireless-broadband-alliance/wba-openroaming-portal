<?php

namespace App\Command;

use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
use App\Service\RegistrationEmailGenerator;
use App\Service\SendSMS;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
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
    private SettingRepository $settingRepository;
    private UserRadiusProfileRepository $userRadiusProfileRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        PgpEncryptionService $pgpEncryptionService,
        SendSMS $sendSMS,
        ProfileManager $profileManager,
        RegistrationEmailGenerator $registrationEmailGenerator,
        UserExternalAuthRepository $userExternalAuthRepository,
        SettingRepository $settingRepository,
        UserRadiusProfileRepository $userRadiusProfileRepository
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->pgpEncryptionService = $pgpEncryptionService;
        $this->sendSMS = $sendSMS;
        $this->profileManager = $profileManager;
        $this->registrationEmailGenerator = $registrationEmailGenerator;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->settingRepository = $settingRepository;
        $this->userRadiusProfileRepository = $userRadiusProfileRepository;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws NonUniqueResultException
     * @throws ClientExceptionInterface
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

            $provider = $userExternalAuth->getProvider();
            $providerId = $userExternalAuth->getProviderId();
            $timeToExpire = 90;

            // Determine expiration based on provider and provider ID
            switch ($provider) {
                case UserProvider::GOOGLE_ACCOUNT:
                    $settingTime = $this->settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_GOOGLE']);
                    $timeToExpire = $settingTime ? (int)$settingTime->getValue() : $timeToExpire;
                    break;

                case UserProvider::SAML:
                    $settingTime = $this->settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_SAML']);
                    $timeToExpire = $settingTime ? (int)$settingTime->getValue() : $timeToExpire;
                    break;

                case UserProvider::PORTAL_ACCOUNT:
                    if ($providerId === UserProvider::EMAIL) {
                        $settingTime = $this->settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_EMAIL']);
                        $timeToExpire = $settingTime ? (int)$settingTime->getValue() : $timeToExpire;
                    } elseif ($providerId === UserProvider::PHONE_NUMBER) {
                        $settingTime = $this->settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_SMS']);
                        $timeToExpire = $settingTime ? (int)$settingTime->getValue() : $timeToExpire;
                    }
                    break;
            }

            $timeToNotify = round($timeToExpire * 0.9);

            // Calculate time thresholds
            /** @phpstan-ignore-next-line */
            $limitTime = (clone $userRadiusProfile->getIssuedAt())->modify("+ {$timeToExpire} days");
            /** @phpstan-ignore-next-line */
            $alertTime = (clone $userRadiusProfile->getIssuedAt())->modify("+ {$timeToNotify} days");
            $realTime = new DateTime();

            $timeLeft = $limitTime->diff($realTime);
            $timeLeftDays = $timeLeft->invert === 0 ? $timeLeft->days + 1 : 0;

            // Notify user if within alert window
            if (
                $realTime >= $alertTime &&
                $realTime <= $limitTime &&
                $userRadiusProfile->getStatus() === 1
            ) {
                if ($user->getEmail()) {
                    $this->registrationEmailGenerator->sendNotifyExpiresProfileEmail($user, $timeLeftDays);
                }
                if ($user->getPhoneNumber()) {
                    $this->sendSMS->sendSms(
                        $user->getPhoneNumber(),
                        'Your profile will expire in ' . $timeLeftDays . ' days.'
                    );
                }
            }

            // Disable profile if expired
            if (
                $realTime > $limitTime &&
                $userRadiusProfile->getStatus() === 1
            ) {
                $this->disableProfiles($user);
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
