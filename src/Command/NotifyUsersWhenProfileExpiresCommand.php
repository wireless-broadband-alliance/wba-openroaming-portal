<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\UserRadiusProfile;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
use App\Service\RegistrationEmailGenerator;
use App\Service\SendSMS;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

    public function notifyUsersWhenProfileExpires (OutputInterface $output): void
    {
        $userRadiusProfileRepository = $this->entityManager->getRepository(UserRadiusProfile::class);
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $userRadiusProfiles = $userRadiusProfileRepository->findAll();
        $settingTime = $settingsRepository->findBy(['name' => 'USER_NOTIFY_TIME']);
        $timeString = $settingTime[0]->getValue();
        $timeToExpire = (int)$timeString;
        // 3 dias, profile menager tem uma função para desativar os perfis
        $notifyTime = 3;
        $timeToNotify = $timeToExpire - $notifyTime;
        foreach ($userRadiusProfiles as $userRadiusProfile) {
            $limitTime = $userRadiusProfile->getIssuedAt();
            $alertTime = $userRadiusProfile->getIssuedAt();
            /** @var \DateTime $limitTime */
            $realTime = new \DateTime();
            $limitTime->modify("+ {$timeToExpire} days");
            /** @var \DateTime $alertTime */
            $alertTime->modify("+ {$timeToExpire} days");
            if (($alertTime < $realTime) and ($limitTime > $realTime) and $userRadiusProfile->getStatus() == 1)
            {
                $user = $userRadiusProfile->getUser();
                if ($user->getEmail()) {
                    $output->writeln('email enviado pelo user ' . $user->getUuid() . ' pelo profile ' . $userRadiusProfile->getId()) ;
                    $this->registrationEmailGenerator->sendNotifyExpiresProfileEmail($user);
                }
                elseif ($user->getPhoneNumber()) {
                    $output->writeln('sms enviado pelo user ' . $user->getUuid() . ' pelo profile ' . $userRadiusProfile->getId());
                    $this->sendSMS->sendSms($user->getPhoneNumber(), 'your profile will expire within 3 days');
                }
            }
            if ($limitTime < $realTime and $userRadiusProfile->getStatus() == 1)
            {
                $user = $userRadiusProfile->getUser();
                $this->profileManager->disableProfiles($user);
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notifyUsersWhenProfileExpires($output);
        $output->writeln('Users notified');

        return Command::SUCCESS;
    }

}
