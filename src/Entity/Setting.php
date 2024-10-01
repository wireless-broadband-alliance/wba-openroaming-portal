<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Api\V1\Controller\ConfigController;
use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ApiResource(
    description: "The Setting entity returns configuration options for the application. 
    Each setting consists of a name and an optional value, 
    which can be used to store and return configuration parameters required for the API.",
    operations: [
        new GetCollection(
            uriTemplate: '/v1/config',
            controller: ConfigController::class,
            openapiContext: [
                'security' => [],
                'summary' => 'Get configuration settings',
                'description' => 'This endpoint returns public values from the Setting entity and 
                environment variables categorized by platform and provider.',
                'responses' => [
                    '200' => [
                        'description' => 'Configuration settings retrieved successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => true,
                                        ],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'platform' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'PLATFORM_MODE' => ['type' => 'boolean'],
                                                        'USER_VERIFICATION' => ['type' => 'boolean'],
                                                        'TURNSTILE_CHECKER' => ['type' => 'boolean'],
                                                        'CONTACT_EMAIL' => ['type' => 'string'],
                                                        'TOS_LINK' => ['type' => 'string'],
                                                        'PRIVACY_POLICY_LINK' => ['type' => 'string'],
                                                    ],
                                                ],
                                                'auth' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'AUTH_METHOD_SAML_ENABLED' => ['type' => 'boolean'],
                                                        'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => ['type' => 'boolean'],
                                                        'AUTH_METHOD_REGISTER_ENABLED' => ['type' => 'boolean'],
                                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                                        'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' => ['type' => 'boolean'],
                                                        // phpcs:enable
                                                        'AUTH_METHOD_SMS_REGISTER_ENABLED' => ['type' => 'boolean'],
                                                    ],
                                                ],
                                                'turnstile' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'TURNSTILE_KEY' => ['type' => 'string'],
                                                    ],
                                                ],
                                                'google' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'GOOGLE_CLIENT_ID' => ['type' => 'string'],
                                                    ],
                                                ],
                                                'saml' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'SAML_IDP_ENTITY_ID' => ['type' => 'string'],
                                                        'SAML_IDP_SSO_URL' => ['type' => 'string'],
                                                        'SAML_IDP_X509_CERT' => ['type' => 'string'],
                                                        'SAML_SP_ENTITY_ID' => ['type' => 'string'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        'platform' => [
                                            'PLATFORM_MODE' => "Live",
                                            'USER_VERIFICATION' => true,
                                            'TURNSTILE_CHECKER' => true,
                                            'CONTACT_EMAIL' => 'support@example.com',
                                            'TOS_LINK' => 'https://example.com/tos',
                                            'PRIVACY_POLICY_LINK' => 'https://example.com/privacy',
                                        ],
                                        'auth' => [
                                            'AUTH_METHOD_SAML_ENABLED' => true,
                                            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => true,
                                            'AUTH_METHOD_REGISTER_ENABLED' => true,
                                            'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' => true,
                                            'AUTH_METHOD_SMS_REGISTER_ENABLED' => true,
                                        ],
                                        'turnstile' => [
                                            'TURNSTILE_KEY' => 'example_turnstile_key',
                                        ],
                                        'google' => [
                                            'GOOGLE_CLIENT_ID' => 'example_google_client_id',
                                        ],
                                        'saml' => [
                                            'SAML_IDP_ENTITY_ID' => 'https://example.com/saml/metadata',
                                            'SAML_IDP_SSO_URL' => 'https://example.com/saml/sso',
                                            'SAML_IDP_X509_CERT' => 'MIIC...AB',
                                            'SAML_SP_ENTITY_ID' => 'https://example.com/saml/sp',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            shortName: 'Setting',
            paginationEnabled: false,
            description: 'Returns public values from the Setting entity',
            name: 'app_config_settings',
        ),
    ],
)]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $value = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }
}
