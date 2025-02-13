<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\UserRadiusProfileStatus;
use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Repository\UserRepository;

class ProfileManager
{
    public function __construct(
        private readonly UserRadiusProfileRepository $userRadiusProfile,
        private readonly RadiusUserRepository $radiusUserRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    private function updateProfiles(User $user, callable $updateCallback): void
    {
        // reducing duplicated code
        $profiles = $user->getUserRadiusProfiles();
        foreach ($profiles as $profile) {
            if ($updateCallback($profile)) {
                $this->userRadiusProfile->save($profile);
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
            $this->userRadiusProfile->save($profile);
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
        if (!$user->isDisabled()) {
            return;
        }

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
                $radiusUser->setValue($profile->getRadiusToken());
                $this->radiusUserRepository->save($radiusUser);
            }
            $profile->setStatus(UserRadiusProfileStatus::ACTIVE->value);
            $this->userRadiusProfile->save($profile);

            return true;
        });
        $user->setDisabled(false);
        $this->userRepository->save($user, true);
        $this->radiusUserRepository->flush();
    }
}
