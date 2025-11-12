<?php

namespace App\Service;

use App\Enum\UserProvider;
use App\Repository\UserRepository;

readonly class UserProviderDetectorResolverService
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    /**
     * Resolves the username and login type from uuid, email, or phone number.
     *
     * @param string $uuid
     * @return array{uuidType: string}
     */
    public function resolve(string $uuid): array
    {
        $uuidType = null;

        if ($uuid) {
            $user = $this->userRepository->findOneBy(['uuid' => $uuid]);

            if ($user) {
                foreach ($user->getUserExternalAuths() as $externalAuth) {
                    $providerId = $externalAuth->getProviderId();
                    if ($providerId === UserProvider::EMAIL->value) {
                        $uuidType = UserProvider::EMAIL->value;
                        break;
                    }

                    if ($providerId === UserProvider::PHONE_NUMBER->value) {
                        $uuidType = UserProvider::PHONE_NUMBER->value;
                        break;
                    }
                }
            }
        }

        return [
            'uuidType' => $uuidType,
        ];
    }
}
