<?php

// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;

use function is_string;

class CustomSamlUserFactory implements SamlUserFactoryInterface
{
    /**
     * Default attribute mapping.
     */
    private const ATTRIBUTE_MAPPING = [
        'password' => 'notused',
        'uuid' => '$samlUuid',
        'email' => '$email',
        'first_name' => '$givenName',
        'last_name' => '$surname',
        'isVerified' => 1,
        'roles' => [],
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function createUser(string $identifier, array $attributes): UserInterface
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        if ($data['PLATFORM_MODE']['value'] === true) {
            throw new RuntimeException(
                "Get Away. 
            It's impossible to use this authentication method in demo mode"
            );
        }

        $uuid = $this->getAttributeValue($attributes, 'samlUuid');
        $samlIdentifier = $this->getAttributeValue($attributes, 'sAMAccountName');

        // Check if the user already exists
        $existingUser = $this->userRepository->findOneBy(['uuid' => $uuid]);

        if ($existingUser) {
            if ($existingUser->isDisabled()) {
                throw new \RuntimeException('User Disabled');
            }
            return $existingUser;
        }

        // Instead of userClass and mapping, use App\Entity\User directly
        $user = new User();
        $reflection = new ReflectionClass(User::class); // Hardcoded User entity

        /** @psalm-suppress MixedAssignment */
        foreach (self::ATTRIBUTE_MAPPING as $field => $attribute) {
            $property = $reflection->getProperty($field);
            $value = null;

            if (is_string($attribute) && str_starts_with($attribute, '$')) {
                try {
                    $value = $this->getAttributeValue($attributes, substr($attribute, 1));
                } catch (RuntimeException) {
                    if ($field === 'email') {
                        $value = null;
                    }
                }
            } else {
                $value = $attribute;
            }

            $property->setValue($user, $value);
        }

        $user->setDisabled(false);
        // Create a new UserExternalAuth entity
        $userAuth = new UserExternalAuth();
        $userAuth->setUser($user)
            ->setProvider(UserProvider::SAML)
            ->setProviderId($samlIdentifier);

        // Persist the external auth entity
        $this->entityManager->persist($user);
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        return $user;
    }

    private function getAttributeValue(array $attributes, string $attribute): mixed
    {
        $isArrayValue = str_ends_with($attribute, '[]');
        $attribute = $isArrayValue ? substr($attribute, 0, -2) : $attribute;

        if (!\array_key_exists($attribute, $attributes)) {
            throw new RuntimeException('Attribute "' . $attribute . '" not found in SAML data.');
        }

        $attributeValue = (array)$attributes[$attribute];
        if (!$isArrayValue) {
            /** @psalm-suppress MixedAssignment */
            $attributeValue = reset($attributeValue);
        }

        return $attributeValue;
    }
}
