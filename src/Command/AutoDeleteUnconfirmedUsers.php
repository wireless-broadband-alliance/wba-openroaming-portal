<?php

namespace App\Command;

use App\Entity\DeletedUserData;
use App\Entity\Event;
use App\Entity\Setting;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:delete_unconfirmed_users',
    description: 'Allocate providers info from User Entity to the UserExternalAuth Entity',
)]

class AutoDeleteUnconfirmedUsers extends Command
{
    private EntityManagerInterface $entityManager;
    public ProfileManager $profileManager;
    public PgpEncryptionService $pgpEncryptionService;

    public function __construct(
        EntityManagerInterface $entityManager,
        PgpEncryptionService $pgpEncryptionService,
        ProfileManager $profileManager
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->pgpEncryptionService = $pgpEncryptionService;
        $this->profileManager = $profileManager;
    }

    public function deleteUnconfirmedUsers(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);
        $users = $userRepository->findAll();
        $settingTime = $settingsRepository->findBy(['name' => 'USER_DELETE_TIME']);
        foreach ($users as $user) {
            $timeString = $settingTime[0]->getValue();
            $time = (int)$timeString;
            $limitTime = $user->getCreatedAt();
            $limitTime->modify("+ {$time} hours");
            $realTime = new \DateTime();
            if (!($user->isVerified() and !$user->isDisabled())) {
                if ($limitTime < $realTime) {
                    // Prepare user data for encryption
                    $deletedUserData = [
                        'id' => $user->getId(),
                        'uuid' => $user->getUuid(),
                        'email' => $user->getEmail() ?? 'This value is empty',
                        'phoneNumber' => $user->getPhoneNumber() ?? 'This value is empty',
                        'firstName' => $user->getFirstName() ?? 'This value is empty',
                        'lastName' => $user->getLastName() ?? 'This value is empty',
                        'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                        'bannedAt' => $user->getBannedAt() ? $user->getBannedAt()->format('Y-m-d H:i:s') : null,
                        'deletedAt' => new DateTime(),
                    ];
                    $userExternalAuths = $userExternalAuthRepository->findBy(['user' => $user->getId()]);
                    // Prepare external auth data for encryption

                    $deletedUserExternalAuthData = [];
                    foreach ($userExternalAuths as $externalAuth) {
                        $deletedUserExternalAuthData[] = [
                            'provider' => $externalAuth->getProvider(),
                            'providerId' => $externalAuth->getProviderId()
                        ];
                    }

                    // Combine user data and external auth data
                    $combinedData = [
                        'user' => $deletedUserData,
                        'externalAuths' => $deletedUserExternalAuthData,
                    ];
                    $jsonDataCombined = json_encode($combinedData);

                    // Encrypt combined JSON data using PGP encryption
                    $pgpEncryptedService = new PgpEncryptionService();
                    $pgpEncryptedData = $this->pgpEncryptionService->encrypt($jsonDataCombined);

                    // Persist encrypted data
                    $deletedUserData = new DeletedUserData();
                    $deletedUserData->setPgpEncryptedJsonFile($pgpEncryptedData);
                    $deletedUserData->setUser($user);

                    $event = new Event();
                    $event->setUser($user);
                    $event->setEventDatetime(new DateTime());
                    $event->setEventName(AnalyticalEventType::DELETED_USER_BY);


                    // Update user entity
                    $user->setUuid($user->getId());
                    $user->setEmail('');
                    $user->setPhoneNumber(null);
                    $user->setPassword($user->getId());
                    $user->setFirstName(null);
                    $user->setLastName(null);
                    $user->setDeletedAt(new DateTime());

                    // Update external auth entity
                    foreach ($userExternalAuths as $externalAuth) {
                        $this->entityManager->remove($externalAuth);
                    }

                    // Persist changes
                    $this->disableProfiles($user);
                    $this->entityManager->persist($deletedUserData);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }
            }
        }
    }

    private function disableProfiles($user): void
    {
        $this->profileManager->disableProfiles($user);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->deleteUnconfirmedUsers();
        $output->writeln('Users deleted');

        return Command::SUCCESS;
    }
}
