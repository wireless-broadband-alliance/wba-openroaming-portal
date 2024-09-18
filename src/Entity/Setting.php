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
                'summary' => 'Get configuration settings',
                'description' => 'This endpoint returns public values from the Setting entity and 
                environment variables categorized by platform and provider. It requires a valid CAPTCHA token.',
                'responses' => [
                    '200' => [
                        'description' => 'Configuration settings retrieved successfully',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'platform' => [
                                        'PLATFORM_MODE' => true,
                                        'USER_VERIFICATION' => false,
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
                                        'AUTH_METHOD_SMS_REGISTER_ENABLED' => false,
                                    ],
                                    'turnstile' => [
                                        'TURNSTILE_KEY' => 'turnstile_key',
                                    ],
                                    'google' => [
                                        'GOOGLE_CLIENT_ID' => 'google_client_id',
                                    ],
                                    'sentry' => [
                                        'SENTRY_DSN' => 'sentry_dsn',
                                    ],
                                    'saml' => [
                                        'SAML_IDP_ENTITY_ID' => 'saml_idp_entity_id',
                                        'SAML_IDP_SSO_URL' => 'saml_idp_sso_url',
                                        'SAML_IDP_X509_CERT' => 'saml_idp_x509_cert',
                                        'SAML_SP_ENTITY_ID' => 'saml_sp_entity_id',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            shortName: 'Setting',
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

    /**
     * The settings name
     */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * The settings value
     */
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
