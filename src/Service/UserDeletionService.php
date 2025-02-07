<?php

namespace App\Service;

use App\Entity\DeletedUserData;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\UserVerificationStatus;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumber;
use Symfony\Component\HttpFoundation\Request;

class UserDeletionService
{
    public function __construct(
        private readonly ProfileManager $profileManager,
        private readonly EventActions $eventActions,
        private readonly EntityManagerInterface $entityManager,
        private readonly PgpEncryptionService $encryptionService,
    ) {
    }

    public function deleteUser(User $user, array $userExternalAuths, Request $request, User $currentUser): array
    {
        $phoneNumber = null;
        if ($user->getPhoneNumber() instanceof PhoneNumber) {
            $phoneNumber = "+" .
                $user->getPhoneNumber()->getCountryCode() .
                $user->getPhoneNumber()->getNationalNumber();
        }

        $deletedUserData = [
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail() ?? 'This value is empty',
            'phoneNumber' => $phoneNumber ?? 'This value is empty',
            'firstName' => $user->getFirstName() ?? 'This value is empty',
            'lastName' => $user->getLastName() ?? 'This value is empty',
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'bannedAt' => $user->getBannedAt()?->format('Y-m-d H:i:s'),
            'deletedAt' => new DateTime(),
        ];

        $deletedUserExternalAuthData = [];
        foreach ($userExternalAuths as $externalAuth) {
            $deletedUserExternalAuthData[] = [
                'provider' => $externalAuth->getProvider(),
                'providerId' => $externalAuth->getProviderId()
            ];
        }

        $combinedData = [
            'user' => $deletedUserData,
            'externalAuths' => $deletedUserExternalAuthData,
        ];
        $jsonDataCombined = json_encode($combinedData, JSON_THROW_ON_ERROR);

        $pgpEncryptedData = $this->encryptionService->encrypt($jsonDataCombined);

        if ($pgpEncryptedData[0] === UserVerificationStatus::MISSING_PUBLIC_KEY_CONTENT) {
            return ['success' => false, 'message' => 'Public key is missing. Please provide one.'];
        }

        if ($pgpEncryptedData[0] === UserVerificationStatus::EMPTY_PUBLIC_KEY_CONTENT) {
            return ['success' => false, 'message' => 'Public key is empty. Please provide valid key content.'];
        }

        $deletedUserDataEntity = new DeletedUserData();
        $deletedUserDataEntity->setPgpEncryptedJsonFile($pgpEncryptedData);
        $deletedUserDataEntity->setUser($user);

        $user->setUuid($user->getId());
        $user->setEmail(null);
        $user->setPhoneNumber(null);
        $user->setPassword($user->getId());
        $user->setFirstName(null);
        $user->setLastName(null);
        $user->setDeletedAt(new DateTime());

        foreach ($userExternalAuths as $externalAuth) {
            $this->entityManager->remove($externalAuth);
        }

        $this->profileManager->disableProfiles($user);

        // Persist changes
        $this->entityManager->persist($deletedUserDataEntity);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $eventMetadata = [
            'uuid' => $user->getUuid(),
            'deletedBy' => $currentUser->getUuid(),
            'ip' => $request->getClientIp(),
        ];
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::DELETED_USER_BY,
            new DateTime(),
            $eventMetadata
        );

        return [
            'success' => true,
            'message' => 'User data successfully deleted and encrypted.'
        ];
    }
}
