<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Api\V1\Controller\ConfigController;
use App\Api\V1\Controller\ProfileController;
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
            openapi: new Operation(
                responses: [
                    200 => [
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
                                                        'TOS' => ['type' => 'string'],
                                                        'PRIVACY_POLICY' => ['type' => 'string'],
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
                                            'TOS' => 'LINK',
                                            'PRIVACY_POLICY' => 'LINK',
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
                summary: 'Get configuration settings',
                description: 'This endpoint returns public values from the Setting entity and 
                environment variables categorized by platform and provider.',
                security: [],
            ),
            shortName: 'Setting',
            paginationEnabled: false,
            description: 'Returns public values from the Setting entity',
            name: 'app_config_settings',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new GetCollection(
            uriTemplate: '/v1/config/profile/android',
            controller: ProfileController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Profile configuration for Android successfully retrieved',
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
                                                'config_android' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'radiusUsername' => ['type' => 'string'],
                                                        'radiusPassword' => ['type' => 'string'],
                                                        'friendlyName' => ['type' => 'string'],
                                                        'fqdn' => ['type' => 'string'],
                                                        'roamingConsortiumOis' => [
                                                            'type' => 'array',
                                                            'items' => ['type' => 'string'],
                                                        ],
                                                        'eapType' => ['type' => 'string'],
                                                        'nonEapInnerMethod' => ['type' => 'string'],
                                                        'realm' => ['type' => 'string'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        'config_android' => [
                                            'radiusUsername' => 'user123',
                                            'radiusPassword' => 'encrypted_password_here',
                                            'friendlyName' => 'My Android Profile',
                                            'fqdn' => 'example.com',
                                            'roamingConsortiumOis' => ['5a03ba0000', '004096'],
                                            'eapType' => '21',
                                            'nonEapInnerMethod' => 'MS-CHAP-V2',
                                            'realm' => 'example.com',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid or missing public key',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => ['type' => 'string', 'example' => 'Invalid or missing public key'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    401 => [
                        'description' => 'JWT Token is invalid',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => ['type' => 'string', 'example' => 'JWT Token is invalid!'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Unauthorized access',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => ['type' => 'string', 'example' => 'Unauthorized access!'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Failed to encrypt the password',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Failed to encrypt the password'
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Get Android profile configuration',
                // phpcs:disable Generic.Files.LineLength.TooLong
                description: 'This endpoint retrieves the profile configuration for Android, including a user\'s radius profile data, encrypted password, and other relevant settings for the Android application.',
                // phpcs:enable
                parameters: [
                    new Parameter(
                        name: 'Authorization',
                        in: 'header',
                        description: 'Bearer token required for authentication. Use the format: `Bearer <JWT token>`.',
                        required: true,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
                requestBody: new RequestBody(
                    description: 'Android public key required for radius password encryption. 
                    The request should be sent as JSON with the PGP public_key included in the body.',
                    content: new \ArrayObject([
                        'application/json' => new \ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'public_key' => [
                                        'type' => 'string',
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'description' => 'The RSA public key used for encryption. It must include the full BEGIN/END markers and the key content.',
                                        'example' => '-----BEGIN PUBLIC KEY-----\n<RSA_PUBLIC_KEY>\n-----END PUBLIC KEY-----',
                                        // phpcs:enable
                                    ],
                                ],
                                'required' => ['public_key'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [
                    [
                        'bearerAuth' => [],
                    ]
                ],
            ),
            shortName: 'Profile Configuration',
            paginationEnabled: false,
            description: 'Returns the configuration data for an Android profile',
            name: 'api_config_profile_android',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new GetCollection(
            uriTemplate: '/v1/config/profile/ios',
            controller: ProfileController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Profile configuration for iOS successfully retrieved',
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
                                                'payloadIdentifier' => ['type' => 'string'],
                                                'payloadType' => ['type' => 'string'],
                                                'payloadUUID' => ['type' => 'string'],
                                                'domainName' => ['type' => 'string'],
                                                'EAPClientConfiguration' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'acceptEAPTypes' => ['type' => 'string'],
                                                        'radiusUsername' => ['type' => 'string'],
                                                        'radiusPassword' => ['type' => 'string'],
                                                        'outerIdentity' => ['type' => 'string'],
                                                        'TTLSInnerAuthentication' => ['type' => 'string'],
                                                    ],
                                                ],
                                                'encryptionType' => ['type' => 'string'],
                                                'roamingConsortiumOis' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                ],
                                                'NAIRealmNames' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        'payloadIdentifier' => 'com.apple.wifi.managed.<random_payload_identifier>-2',
                                        'payloadType' => 'com.apple.wifi.managed',
                                        'payloadUUID' => '<random_payload_identifier>-1',
                                        'domainName' => 'example.com',
                                        'EAPClientConfiguration' => [
                                            'acceptEAPTypes' => '21',
                                            'radiusUsername' => 'user123',
                                            'radiusPassword' => 'encrypted_password_here',
                                            'outerIdentity' => 'anonymous@example.com',
                                            'TTLSInnerAuthentication' => 'MSCHAPv2',
                                        ],
                                        'encryptionType' => 'WPA2',
                                        'roamingConsortiumOis' => ['5A03BA0000', '004096'],
                                        'NAIRealmNames' => 'example.com',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid or missing public key',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => ['type' => 'string', 'example' => 'Invalid or missing public key'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    401 => [
                        'description' => 'JWT Token is invalid',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => ['type' => 'string', 'example' => 'JWT Token is invalid!'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Unauthorized access',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => ['type' => 'string', 'example' => 'Unauthorized access!'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Failed to encrypt the password',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Failed to encrypt the password'
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Get iOS profile configuration',
                description: 'This endpoint retrieves the profile configuration for iOS, 
                including a user\'s radius profile data, encrypted password, 
                and other relevant settings for the iOS application.',
                parameters: [
                    new Parameter(
                        name: 'Authorization',
                        in: 'header',
                        description: 'Bearer token required for authentication. Use the format: `Bearer <JWT token>`.',
                        required: true,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
                requestBody: new RequestBody(
                    description: 'iOS public key required for radius password encryption. 
            The request should be sent as JSON with the PGP public_key included in the body.',
                    content: new \ArrayObject([
                        'application/json' => new \ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'public_key' => [
                                        'type' => 'string',
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'description' => 'The RSA public key used for encryption. It must include the full BEGIN/END markers and the key content.',
                                        'example' => '-----BEGIN PUBLIC KEY-----\n<RSA_PUBLIC_KEY>\n-----END PUBLIC KEY-----',
                                        // phpcs:enable
                                    ],
                                ],
                                'required' => ['public_key'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [
                    [
                        'bearerAuth' => [],
                    ],
                ],
            ),
            shortName: 'Profile Configuration',
            paginationEnabled: false,
            description: 'Returns the configuration data for an iOS profile',
            name: 'api_config_profile_ios',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
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
