<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\UserRadiusProfile;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
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

    public function __construct(
        EntityManagerInterface $entityManager,
        PgpEncryptionService $pgpEncryptionService,
        ProfileManager $profileManager
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->pgpEncryptionService = $pgpEncryptionService;
        $this->profileManager = $profileManager;
    }

    public function notifyUsersWhenProfileExpires (OutputInterface $output): void
    {
        $userRadiusProfileRepository = $this->entityManager->getRepository(UserRadiusProfile::class);
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $userRadiusProfiles = $userRadiusProfileRepository->findAll();
        $settingTime = $settingsRepository->findBy(['name' => 'USER_NOTIFY_TIME']);
        $timeString = $settingTime[0]->getValue();
        $time = (int)$timeString;
        foreach ($userRadiusProfiles as $userRadiusProfile) {
            $limitTime = $userRadiusProfile->getIssuedAt();
            /** @var \DateTime $limitTime */
            $realTime = new \DateTime();
            $limitTime->modify("+ {$time} days");
            if ($limitTime < $realTime)
            {
                $user = $userRadiusProfile->getUser();
                if ($user->getEmail()) {
                    $output->writeln('email enviado pelo user ' . $user->getUuid() . ' pelo profile ' . $userRadiusProfile->getId()) ;
                }
                elseif ($user->getPhoneNumber()) {
                    $output->writeln('sms enviado pelo user ' . $user->getUuid() . ' pelo profile ' . $userRadiusProfile->getId());
                }
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
