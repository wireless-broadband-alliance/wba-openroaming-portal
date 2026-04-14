<?php

// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace App\Security;

use ApiPlatform\Metadata\UrlGeneratorInterface;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CustomSamlUserFactory implements SamlUserFactoryInterface
{
    /**
     * Default attribute mapping.
     * @var array<string, int|string|list<string>>
     */
    private readonly array $attribute_mapping;
    private readonly SessionInterface $session;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly SettingRepository $settingRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        $this->session = $requestStack->getSession();
        $this->attribute_mapping = $this->parameterBag->get('app.saml_attribute_mapping') ?? [];
    }

    /**
     * @param array<string, array<int, string>> $attributes
     */
    public function createUser(string $identifier, array $attributes): UserInterface
    {
        $platformModeStatus = $this->settingRepository->findOneBy([
            'name' => SettingName::PLATFORM_MODE
        ]);

        if ($platformModeStatus->getValue() === PlatformMode::DEMO->value) {
            throw new RuntimeException(
                $this->translator->trans(
                    'impossibleUseThisAuthenticationMethodInDemoMode',
                    [],
                    'Security'
                )
            );
        }

        /**
         * ---------------------------------------------------------
         * Resolve identifier (UUID)
         * ---------------------------------------------------------
         */
        $uuidAttribute = $this->attribute_mapping['uuid'] ?? null;
        if (!$uuidAttribute) {
            throw new RuntimeException('SAML uuid mapping is missing');
        }

        $uuid = $this->getAttributeValue($attributes, $uuidAttribute);

        /**
         * ---------------------------------------------------------
         * Check existing user
         * ---------------------------------------------------------
         */
        $existingUser = $this->userRepository->findOneBy(['uuid' => $uuid]);
        if ($existingUser) {
            if ($existingUser->isDisabled()) {
                $this->session->getFlashBag()->add(
                    'error',
                    $this->translator->trans('accountDisabled', [], 'Security')
                );

                $redirect = new RedirectResponse(
                    $this->urlGenerator->generate('app_landing')
                );

                $redirect->send();
                exit;
            }
            return $existingUser;
        }

        $user = new User();
        $user->setUuid($uuid);

        $email = isset($this->attribute_mapping['email'])
            ? $this->getAttributeValue($attributes, $this->attribute_mapping['email'])
            : null;

        $firstName = isset($this->attribute_mapping['first_name'])
            ? $this->getAttributeValue($attributes, $this->attribute_mapping['first_name'])
            : null;

        $lastName = isset($this->attribute_mapping['last_name'])
            ? $this->getAttributeValue($attributes, $this->attribute_mapping['last_name'])
            : null;

        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPassword('notused');
        $user->setIsVerified(true);
        $user->setRoles([]);
        $user->setDisabled(false);
        $user->setCreatedAt(new DateTime());

        $samlIdentifierAttribute = $this->parameterBag->get('app.saml_identifier_attribute');
        $samlIdentifier = $this->getAttributeValue(
            $attributes,
            $samlIdentifierAttribute
        );

        $userAuth = new UserExternalAuth();
        $userAuth->setUser($user)
            ->setProvider(UserProvider::SAML->value)
            ->setProviderId($samlIdentifier);

        $this->entityManager->persist($user);
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param array<string, array<int, string>> $attributes
     */
    private function getAttributeValue(array $attributes, string $attribute): mixed
    {
        $isArrayValue = str_ends_with($attribute, '[]');
        $attribute = $isArrayValue ? substr($attribute, 0, -2) : $attribute;

        if (!isset($attributes[$attribute])) {
            throw new RuntimeException(
                sprintf(
                    'Missing SAML attribute "%s". Available: %s',
                    $attribute,
                    implode(', ', array_keys($attributes))
                )
            );
        }

        $value = $attributes[$attribute];

        if (!$isArrayValue) {
            $value = reset($value);
        }

        return $value;
    }
}
