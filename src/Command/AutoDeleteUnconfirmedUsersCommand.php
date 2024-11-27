<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Entity\UserRadiusProfile;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Symfony\Component\String\u;

#[AsCommand(
    name: 'clear:deleteUnconfirmedUsers',
    description: 'Delete unconfirmed users when timeout exceeded',
)]
class AutoDeleteUnconfirmedUsersCommand extends Command
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

    public function deleteUnconfirmedUsers(): int
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);
        $userRadiusProfileRepository = $this->entityManager->getRepository(UserRadiusProfile::class);
        $users = $userRepository->findAll();
        $settingTime = $settingsRepository->findBy(['name' => 'USER_DELETE_TIME']);
        $usersDeleted = 0;
        foreach ($users as $user) {
            $timeString = $settingTime[0]->getValue();
            $time = (int)$timeString;
            $limitTime = $user->getCreatedAt();
            /** @var \DateTime $limitTime */
            $limitTime->modify("+ {$time} hours");
            $realTime = new \DateTime();
            if (!($user->isVerified() && !$user->isDisabled())) {
                if ($limitTime < $realTime) {
                    $uuid = $user->getUuid();
                    if (!(u($uuid)->containsAny('-DEMO-'))) {
                        $userExternalAuths = $userExternalAuthRepository->findBy(['user' => $user]);
                        foreach ($userExternalAuths as $userExternalAuth) {
                            $this->entityManager->remove($userExternalAuth);
                        }
                        $userRadiusProfiles = $userRadiusProfileRepository->findBy(['user' => $user]);
                        foreach ($userRadiusProfiles as $userRadiusProfile) {
                            $this->entityManager->remove($userRadiusProfile);
                        }
                        $this->entityManager->remove($user);
                        $usersDeleted++;
                    }
                }
            }
            $this->entityManager->flush();
        }
        return $usersDeleted;
    }

    private function disableProfiles($user): void
    {
        $this->profileManager->disableProfiles($user);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $deletedCount = $this->deleteUnconfirmedUsers();

            $output->writeln(
                "<info>Success:</info> $deletedCount event(s) with null or empty values have been deleted."
            );
        } catch (Exception $e) {
            // Handle any exceptions and roll back in case of an error
            $this->entityManager->rollback();
            $output->writeln('<error>An error occurred:</error> ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
