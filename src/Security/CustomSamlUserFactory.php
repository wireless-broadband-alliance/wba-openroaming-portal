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
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class CustomSamlUserFactory implements SamlUserFactoryInterface
{
    /**
     * Default attribute mapping.
     * @var array<string, int|string|list<string>>
     */
    private readonly array $attribute_mapping;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly SettingRepository $settingRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        $this->attribute_mapping = $this->parameterBag->get('app.saml_attribute_mapping');
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

        $uuidAttribute = $this->attribute_mapping['uuid'] ?? null;
        if (!$uuidAttribute) {
            throw new RuntimeException('SAML uuid mapping is missing');
        }

        /**
         * ---------------------------------------------------------
         * Safe Fallback: Extract standard Name attributes from raw XML
         * if the expected UUID key isn't populated by FriendlyName.
         * ---------------------------------------------------------
         */
        if (!isset($attributes[$uuidAttribute])) {
            $attributes = array_merge($attributes, $this->extractStandardAttributesFromRequest());
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
                $session = $this->requestStack->getSession();
                if (method_exists($session, 'getFlashBag')) {
                    $session->getFlashBag()->add(
                        'error',
                        $this->translator->trans('accountDisabled', [], 'Security')
                    );
                    $redirect = new RedirectResponse(
                        $this->urlGenerator->generate('app_landing')
                    );

                    $redirect->send();
                    exit;
                }
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

        $usernameAttribute = $this->attribute_mapping['username'] ?? '';

        // Check if username attribute is specified and present in SAML data
        if (!empty($usernameAttribute) && isset($attributes[$usernameAttribute])) {
            $samlAccountName = $this->getAttributeValue(
                $attributes,
                $usernameAttribute
            );
        } elseif (isset($attributes['sAMAccountName'][0])) {
            // Fallback to old sAMAccountName if available
            $samlAccountName = $attributes['sAMAccountName'][0];
        } else {
            // Use the email address for Google Suite
            $samlAccountName = $email;
        }

        $userAuth = new UserExternalAuth();
        $userAuth->setUser($user)
            ->setProvider(UserProvider::SAML->value)
            ->setProviderId($samlAccountName);

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
            return reset($value);
        }

        return $value;
    }

    /**
     * Extracts standard attributes from the raw SAMLResponse XML payload when
     * use_attribute_friendly_name is true globally but an IdP only sends standard Names.
     *
     * @return array<string, array<int, string>>
     */
    private function extractStandardAttributesFromRequest(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->request->has('SAMLResponse')) {
            return [];
        }

        $xmlStr = base64_decode((string)$request->request->get('SAMLResponse'), true);
        if (!$xmlStr) {
            return [];
        }

        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadXML($xmlStr);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

            // Locate standard saml:Attribute elements
            $nodes = $xpath->query('//saml:Attribute');
            $extracted = [];

            if ($nodes instanceof DOMNodeList) {
                foreach ($nodes as $node) {
                    if ($node instanceof DOMElement) {
                        $name = $node->getAttribute('Name');
                        if (!$name) {
                            continue;
                        }

                        $values = [];
                        // Extract values safely considering potential XML namespaces
                        $valueNodes = $node->getElementsByTagNameNS(
                            'urn:oasis:names:tc:SAML:2.0:assertion',
                            'AttributeValue'
                        );
                        foreach ($valueNodes as $valueNode) {
                            $values[] = $valueNode->nodeValue;
                        }

                        if ($values === []) {
                            $valueNodes = $node->getElementsByTagName('AttributeValue');
                            foreach ($valueNodes as $valueNode) {
                                $values[] = $valueNode->nodeValue;
                            }
                        }

                        $extracted[$name] = $values;
                    }
                }
            }

            return $extracted;
        } catch (Throwable) {
            // Fail silently to prevent breaking other production flows
            return [];
        }
    }
}
