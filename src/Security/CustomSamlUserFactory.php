<?php

// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;

use function is_string;

class CustomSamlUserFactory implements SamlUserFactoryInterface
{
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    /**
     * @param class-string<UserInterface> $userClass
     * @param array<string, mixed> $mapping
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private readonly string $userClass,
        private readonly array $mapping,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @throws ReflectionException
     */
    public function createUser(string $identifier, array $attributes): UserInterface
    {
        $uuid = $this->getAttributeValue($attributes, 'samlUuid');
        $samlIdentifier = $this->getAttributeValue($attributes, 'sAMAccountName');

        // Check if the user already exists
        $existingUser = $this->userRepository->findOneBy(['uuid' => $uuid]);

        if ($existingUser) {
            return $existingUser;
        }

        $user = new $this->userClass($identifier);
        $reflection = new ReflectionClass($this->userClass);

        /** @psalm-suppress MixedAssignment */
        foreach ($this->mapping as $field => $attribute) {
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
