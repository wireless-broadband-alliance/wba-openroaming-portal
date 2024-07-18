<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\UserRadiusProfileStatus;
use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\UserRadiusProfileRepository;

class ProfileManager
{
    private UserRadiusProfileRepository $userRadiusProfile;
    private RadiusUserRepository $radiusUserRepository;

    public function __construct(
        UserRadiusProfileRepository $userRadiusProfile,
        RadiusUserRepository $radiusUserRepository
    ) {
        $this->userRadiusProfile = $userRadiusProfile;
        $this->radiusUserRepository = $radiusUserRepository;
    }

    private function updateProfiles(User $user, callable $updateCallback): void
    {
        // reducing duplicated code
        $profiles = $user->getUserRadiusProfiles();
        foreach ($profiles as $profile) {
            if ($updateCallback($profile)) {
                $this->userRadiusProfile->save($profile, true);
            }
        }
    }

    public function disableProfiles(User $user): void
    {
        $this->updateProfiles($user, function ($profile) {
            if ($profile->getStatus() !== UserRadiusProfileStatus::ACTIVE) {
                return false;
            }

            $profile->setStatus(UserRadiusProfileStatus::REVOKED);
            $radiusUser = $this->radiusUserRepository->findOneBy(['username' => $profile->getRadiusUser()]);
            if ($radiusUser) {
                $this->radiusUserRepository->remove($radiusUser, true);
            }

            return true;
        });
    }

    public function enableProfiles(User $user): void
    {
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
                $this->radiusUserRepository->save($radiusUser, true);

                $profile->setStatus(UserRadiusProfileStatus::ACTIVE);
            }

            return true;
        });
    }
}
