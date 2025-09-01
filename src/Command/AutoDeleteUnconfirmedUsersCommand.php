<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use DateTime;
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
    public $userPasswordHasher;
    public $eventActions;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly SettingRepository $settingRepository,
    ) {
        parent::__construct();
    }

    public function deleteUnconfirmedUsers(): array
    {
        $users = $this->userRepository->findAll();
        $settingTime = $this->settingRepository->findBy(['name' => 'USER_DELETE_TIME']);

        if (empty($settingTime)) {
            return [];
        }

        $timeString = $settingTime[0]->getValue();
        $time = (int)$timeString;

        $deletedUserUuids = [];

        foreach ($users as $user) {
            $limitTime = clone $user->getCreatedAt(); // clone to avoid modifying original
            /** @var \DateTime $limitTime */
            $limitTime->modify("+{$time} hours");

            $realTime = new DateTime();

            if ($limitTime < $realTime && !($user->isVerified() && !$user->isDisabled())) {
                $uuid = $user->getUuid();
                if (!(u($uuid)->containsAny('-DEMO-'))) {
                    // Remove related external auths
                    $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);
                    foreach ($userExternalAuths as $userExternalAuth) {
                        $this->entityManager->remove($userExternalAuth);
                    }

                    // Remove related radius profiles
                    $userRadiusProfiles = $this->userExternalAuthRepository->findBy(['user' => $user]);
                    foreach ($userRadiusProfiles as $userRadiusProfile) {
                        $this->entityManager->remove($userRadiusProfile);
                    }

                    // Remove the user itself
                    $this->entityManager->remove($user);

                    // Save UUID of deleted user
                    $deletedUserUuids[] = $uuid;
                }
            }
        }

        // Flush once after processing all users
        $this->entityManager->flush();

        return $deletedUserUuids;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        try {
            $deletedUserUuids = $this->deleteUnconfirmedUsers();
            $deletedCount = count($deletedUserUuids);

            // Create fake user only for automation command and for event association
            $dummyUser = $this->userRepository->findOneBy(['uuid' => 'automation_delete_user@example.com']);

            if (!$dummyUser) {
                $dummyUser = new User();
                $dummyUserExternalAuth = new UserExternalAuth();

                $randomPassword = bin2hex(random_bytes(32));
                $hashedPassword = $this->userPasswordHasher->hashPassword($dummyUser, $randomPassword);
                $dummyUser->setPassword($hashedPassword);
                $dummyUser->setUuid('automation_delete_user@example.com');
                $dummyUser->setEmail('automation_delete_user@example.com');
                $dummyUser->setFirstName('automation_delete_user');
                $dummyUser->setIsVerified(true);
                $dummyUser->setDisabled(false);
                $dummyUser->setTwoFAcode(random_int(100000, 999999));
                $dummyUser->setCreatedAt(new DateTime());
                $dummyUser->setTwoFACodeGeneratedAt(new DateTime());
                $dummyUser->setTwoFAcodeIsActive(true);

                $dummyUserExternalAuth->setProvider(UserProvider::PORTAL_ACCOUNT->value);
                $dummyUserExternalAuth->setProviderId(UserProvider::EMAIL->value);
                $dummyUserExternalAuth->setUser($dummyUser);

                $this->entityManager->persist($dummyUser);
                $this->entityManager->persist($dummyUserExternalAuth);
                $this->entityManager->flush();
            }

            $output->writeln(
                "<info>Success:</info> $deletedCount user(s) with unverified accounts have been deleted."
            );
        } catch (Exception $e) {
            // Handle any exceptions and roll back in case of an error
            $this->entityManager->rollback();
            $output->writeln('<error>An error occurred:</error> ' . $e->getMessage());
            return Command::FAILURE;
        }

        $eventMetadata = [
            'deleted_users_uuids' => $deletedUserUuids,
            'deleted_user_counts' => $deletedCount,
            'deleted_by' => $dummyUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $dummyUser,
            AnalyticalEventType::AUTO_DELETE_UNCONFIRMED_ACCOUNTS->value,
            new DateTime(),
            $eventMetadata
        );

        return Command::SUCCESS;
    }
}
