<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\UserRadiusProfileStatus;
use App\Exception\EncryptionException;
use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Repository\UserRepository;

readonly class ProfileManager
{
    public function __construct(
        private UserRadiusProfileRepository $userRadiusProfileRepository,
        private RadiusUserRepository $radiusUserRepository,
        private UserRepository $userRepository,
        private EncryptionService $encryptionService
    ) {
    }

    private function updateProfiles(User $user, callable $updateCallback): void
    {
        $profiles = $user->getUserRadiusProfiles();
        foreach ($profiles as $profile) {
            if ($updateCallback($profile)) {
                $this->userRadiusProfileRepository->save($profile);
            }
        }
    }

    public function disableProfiles(User $user, string $revokedReason, ?bool $skipDisableAccount = null): bool
    {
        if (!$skipDisableAccount && $user->isDisabled()) {
            return false;
        }

        $hasActiveProfiles = false;

        // Pass $revokedReason into the closure
        $this->updateProfiles($user, function ($profile) use (&$hasActiveProfiles, $revokedReason) {
            if ($profile->getStatus() !== UserRadiusProfileStatus::ACTIVE->value) {
                return false;
            }

            $hasActiveProfiles = true; // Mark that there are active profiles to be revoked

            // Set status to REVOKED
            $profile->setStatus(UserRadiusProfileStatus::REVOKED->value);
            $profile->setRevokedReason($revokedReason);

            $radiusUser = $this->radiusUserRepository->findOneBy(['username' => $profile->getRadiusUser()]);
            if ($radiusUser) {
                $this->radiusUserRepository->remove($radiusUser);
            }

            return true;
        });

        if ($hasActiveProfiles && $skipDisableAccount !== true) {
            $user->setDisabled(true);
            $this->userRepository->save($user, true);
        }

        $this->radiusUserRepository->flush();
        return $hasActiveProfiles;
    }

    public function enableProfiles(User $user): void
    {
        $this->updateProfiles($user, function ($profile) {
            if ($profile->getStatus() === UserRadiusProfileStatus::ACTIVE->value) {
                return false;
            }

            $radiusUser = $this->radiusUserRepository->findOneBy(['username' => $profile->getRadiusUser()]);
            if (!$radiusUser) {
                $radiusUser = new RadiusUser();
                $radiusUser->setUsername($profile->getRadiusUser());
                $radiusUser->setAttribute('Cleartext-Password');
                $radiusUser->setOp(':=');

                $plainPassword = $this->decryptToken((string)$profile->getRadiusToken());
                $radiusUser->setValue($plainPassword);

                $this->radiusUserRepository->save($radiusUser);
            }
            $profile->setStatus(UserRadiusProfileStatus::ACTIVE->value);

            return true;
        });

        if ($user->isDisabled()) {
            $user->setDisabled(false);
            $this->userRepository->save($user, true);
        }

        $this->radiusUserRepository->flush();
    }

    /**
     * @return UserRadiusProfile[]
     */
    public function getActiveProfilesByUser(User $user): array
    {
        return $this->userRadiusProfileRepository->findBy([
            'user' => $user,
            'status' => UserRadiusProfileStatus::ACTIVE->value,
        ]);
    }

    /**
     * Decrypt radius token safely, staying backwards-compatible with unencrypted legacy tokens.
     */
    private function decryptToken(string $token): string
    {
        try {
            return $this->encryptionService->decrypt($token);
        } catch (EncryptionException) {
            return $token;
        }
    }
}
