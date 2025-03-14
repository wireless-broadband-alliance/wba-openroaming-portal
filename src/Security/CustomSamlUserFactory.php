<?php

// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace App\Security;

use ApiPlatform\Metadata\UrlGeneratorInterface;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function is_string;

class CustomSamlUserFactory implements SamlUserFactoryInterface
{
    /**
     * Default attribute mapping.
     */
    private readonly array $attribute_mapping;
    private readonly SessionInterface $session;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        RequestStack $requestStack,
    ) {
        $this->session = $requestStack->getSession();
        $this->attribute_mapping = [
            'password' => 'notused',
            'uuid' => '$samlUuid',
            'email' => '$email',
            'first_name' => '$givenName',
            'last_name' => '$surname',
            'isVerified' => 1,
            'roles' => [],
        ];
    }

    /**
     * @throws ReflectionException
     * @throws \Exception
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
                /** @phpstan-ignore-next-line */ // To avoid conflicts with RECTOR
                $this->session->getFlashBag()->add(
                    'error',
                    'This account is disabled. Please contact support.'
                );
                $redirect = new RedirectResponse($this->urlGenerator->generate('app_landing'));
                $redirect->send();
                exit; // Stop further authentication execution
            }
            return $existingUser;
        }

        // Instead of userClass and mapping, use App\Entity\User directly
        $user = new User();
        $reflection = new ReflectionClass(User::class);

        /** @psalm-suppress MixedAssignment */
        foreach ($this->attribute_mapping as $field => $attribute) {
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

        $email = array_key_exists('urn:oid:1.2.840.113549.1.9.1', $attributes)
            ? $attributes['urn:oid:1.2.840.113549.1.9.1'][0]
            : null;

        $firstName = array_key_exists('urn:oid:2.5.4.42', $attributes)
            ? $attributes['urn:oid:2.5.4.42'][0]
            : null;

        $lastName = array_key_exists('urn:oid:2.5.4.4', $attributes)
            ? $attributes['urn:oid:2.5.4.4'][0]
            : null;

        $user->setDisabled(false);
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setCreatedAt(new DateTime());
        // Create a new UserExternalAuth entity
        $userAuth = new UserExternalAuth();
        $userAuth->setUser($user)
            ->setProvider(UserProvider::SAML->value)
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
