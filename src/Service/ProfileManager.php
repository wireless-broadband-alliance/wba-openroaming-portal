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
    private UserRadiusProfileRepository $userRadiusProfile;
    private RadiusUserRepository $radiusUserRepository;
    private UserRepository $userRepository;

    public function __construct(
        UserRadiusProfileRepository $userRadiusProfile,
        RadiusUserRepository $radiusUserRepository,
        UserRepository $userRepository
    ) {
        $this->userRadiusProfile = $userRadiusProfile;
        $this->radiusUserRepository = $radiusUserRepository;
        $this->userRepository = $userRepository;
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

    public function disableProfiles(User $user): void
    {
        if ($user->isDisabled()) {
            return;
        }

        $this->updateProfiles($user, function ($profile) {
            if ($profile->getStatus() !== UserRadiusProfileStatus::ACTIVE) {
                return false;
            }

            $profile->setStatus(UserRadiusProfileStatus::REVOKED);
            $radiusUser = $this->radiusUserRepository->findOneBy(['username' => $profile->getRadiusUser()]);
            if ($radiusUser) {
                $this->radiusUserRepository->remove($radiusUser);
            }
            $this->userRadiusProfile->save($profile);
            return true;
        });
        $user->setDisabled(true);
        $this->userRepository->save($user, true);
    }

    public function enableProfiles(User $user): void
    {
        if (!$user->isDisabled()) {
            return;
        }

        $this->updateProfiles($user, function ($profile) {
            if ($profile->getStatus() === UserRadiusProfileStatus::ACTIVE) {
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
            $profile->setStatus(UserRadiusProfileStatus::ACTIVE);
            $this->userRadiusProfile->save($profile);

            return true;
        });
        $user->setDisabled(false);
        $this->userRepository->save($user, true);
    }
}
