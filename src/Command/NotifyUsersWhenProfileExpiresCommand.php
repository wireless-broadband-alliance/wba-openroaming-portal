<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\UserRadiusProfile;
use App\Enum\UserProvider;
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

    public function __construct(
        EntityManagerInterface $entityManager,
        PgpEncryptionService $pgpEncryptionService,
        SendSMS $sendSMS,
        ProfileManager $profileManager,
        RegistrationEmailGenerator $registrationEmailGenerator
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->pgpEncryptionService = $pgpEncryptionService;
        $this->sendSMS = $sendSMS;
        $this->profileManager = $profileManager;
        $this->registrationEmailGenerator = $registrationEmailGenerator;
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
        $userRadiusProfileRepository = $this->entityManager->getRepository(UserRadiusProfile::class);
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $userRadiusProfiles = $userRadiusProfileRepository->findAll();
        foreach ($userRadiusProfiles as $userRadiusProfile) {
            if ($userRadiusProfile->getUser()) {
                $authenticationMethod = $userRadiusProfile->getUser()->getUserExternalAuths()[0];
            }
            else {
                $authenticationMethod = null;
            }
            if ($authenticationMethod->getProvider() === UserProvider::GOOGLE_ACCOUNT) {
                $settingTime = $settingsRepository->findBy(['name' => 'PROFILE_LIMIT_DATE_GOOGLE']);
                $timeString = $settingTime[0]->getValue();
                $timeToExpire = (int)$timeString;
                $timeToNotify = round($timeToExpire * 0.9);
            } elseif ($authenticationMethod->getProvider() === UserProvider::SAML) {
                $settingTime = $settingsRepository->findBy(['name' => 'PROFILE_LIMIT_DATE_SAML']);
                $timeString = $settingTime[0]->getValue();
                $timeToExpire = (int)$timeString;
                $timeToNotify = round($timeToExpire * 0.9);
            } elseif ($authenticationMethod->getProvider() === UserProvider::PORTAL_ACCOUNT) {
                if ($userRadiusProfile->getUser()->getEmail()) {
                    $settingTime = $settingsRepository->findBy(['name' => 'PROFILE_LIMIT_DATE_EMAIL']);
                    $timeString = $settingTime[0]->getValue();
                    $timeToExpire = (int)$timeString;
                    $timeToNotify = round($timeToExpire * 0.9);
                } elseif ($userRadiusProfile->getUser()->getEmail()) {
                    $settingTime = $settingsRepository->findBy(['name' => 'PROFILE_LIMIT_DATE_SMS']);
                    $timeString = $settingTime[0]->getValue();
                    $timeToExpire = (int)$timeString;
                    $timeToNotify = round($timeToExpire * 0.9);
                } else {
                    $timeToExpire = 90;
                    $timeToNotify = round($timeToExpire * 0.9);
                }
            } else {
                $timeToExpire = 90;
                $timeToNotify = round($timeToExpire * 0.9);
            }
            $limitTime = clone $userRadiusProfile->getIssuedAt();
            $alertTime = clone $userRadiusProfile->getIssuedAt();

            /** @var DateTime $limitTime */
            $realTime = new DateTime();
            $limitTime->modify("+ {$timeToExpire} days");
            /** @var DateTime $alertTime */
            $alertTime->modify("+ {$timeToNotify} days");
            $timeLeft = $limitTime->diff($realTime);
            $timeLeftDays = $timeLeft->days + 1;
            $user = $userRadiusProfile->getUser();
            if ($user) {
                if (($alertTime < $realTime) && ($limitTime > $realTime) && $userRadiusProfile->getStatus() == 1) {
                    if ($user->getEmail()) {
                        $this->registrationEmailGenerator->sendNotifyExpiresProfileEmail($user, $timeLeftDays);
                    } elseif ($user->getPhoneNumber()) {
                        $this->sendSMS->sendSms($user->getPhoneNumber(), 'your profile will expire within ' .
                            $timeLeftDays . ' days');
                    }
                }
            }
            if ($limitTime < $realTime && $userRadiusProfile->getStatus() == 1) {
                $this->disableProfiles($user);
                $this->entityManager->persist($userRadiusProfile);
                $this->entityManager->flush();
            }
        }
    }

    private function disableProfiles($user): void
    {
        $this->profileManager->disableProfiles($user);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notifyUsersWhenProfileExpires($output);
        $output->writeln('Users notified');

        return Command::SUCCESS;
    }
}
