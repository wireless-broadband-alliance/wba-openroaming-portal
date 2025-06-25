<?php

namespace App\Api\V1\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Api\V1\Controller\CapportController;
use App\Api\V1\Controller\ConfigController;
use App\Api\V1\Controller\ProfileController;
use App\Api\V1\Controller\TurnstileController;
use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/turnstile/android',
            controller: TurnstileController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Turnstile HTML configuration retrieved successfully',
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
                                            'type' => 'string',
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'description' => 'The HTML content required for turnstile configuration on the Android app.',
                                            'example' => '<html><body><h1>Turnstile Configuration</h1><p>This is the required HTML configuration for the Android App.</p></body></html>',
                                            //phpcs:enable
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                    'data' => '<html><body><h1>Turnstile Configuration</h1><p>This is the required HTML configuration for the Android App.</p></body></html>',
                                    //phpcs:enable
                                ],
                            ],
                        ],
                    ],
                    404 => [
                        'description' => 'HTML file not found.',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'success' => false,
                                    'error' => 'HTML file not found.',
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Return the HTML required for Turnstile configuration on an Android App',
                // phpcs:disable Generic.Files.LineLength.TooLong
                description: 'This endpoint serves the public HTML configuration required for the Android App to integrate with the Turnstile feature.',
                // phpcs:enable
                security: [],
            ),
            shortName: 'Turnstile',
            paginationEnabled: false,
            description: 'Serves the public HTML configuration required for Turnstile integration for Android apps.',
            name: 'api_v1_turnstile_html_android',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new GetCollection(
            uriTemplate: '/capport/json',
            controller: CapportController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Successful response with CAPPORT metadata.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => true,
                                            'description' => 'Indicates if the request was successful.'
                                        ],
                                        'data' => [
                                            'type' => 'object',
                                            'description' => 'CAPPORT metadata object.',
                                            'properties' => [
                                                'captive' => [
                                                    'type' => 'boolean',
                                                    'example' => false
                                                ],
                                                'user-portal-url' => [
                                                    'type' => 'string',
                                                    'format' => 'uri',
                                                    'example' => 'https://example.com/'
                                                ],
                                                'venue-info-url' => [
                                                    'type' => 'string',
                                                    'format' => 'uri',
                                                    'example' => 'https://openroaming.org/'
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                'example' => [
                                    'captive' => false,
                                    'user-portal-url' => 'https://example.com/',
                                    'venue-info-url' => 'https://openroaming.org/'
                                ]
                            ]
                        ]
                    ],
                    404 => [
                        'description' => 'CAPPORT is not enabled.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => ['type' => 'string', 'example' => 'CAPPORT is not enabled']
                                    ]
                                ],
                                'example' => [
                                    'success' => false,
                                    'error' => 'CAPPORT is not enabled'
                                ]
                            ]
                        ]
                    ]
                ],
                description: 'Returns JSON metadata for the Captive Portal (CAPPORT) configuration.',
                security: []
            ),
            shortName: 'Capport',
            paginationEnabled: false,
            description: 'CAPPORT JSON metadata endpoint',
            name: 'api_v1_capport_json',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new GetCollection(
            uriTemplate: '/config',
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
                                                        'PLATFORM_MODE' => [
                                                            'type' => 'string',
                                                            'enum' => ['Live', 'Demo'],
                                                            'example' => 'Live',
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'The platform mode of the application. Possible values: Live or Demo.',
                                                            // phpcs:enable
                                                        ],
                                                        'USER_VERIFICATION' => [
                                                            'type' => 'string',
                                                            'enum' => ['ON', 'OFF'],
                                                            'example' => 'ON',
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates whether user verification is enabled (ON) or disabled (OFF).',
                                                            // phpcs:enable
                                                        ],
                                                        'TURNSTILE_CHECKER' => [
                                                            'type' => 'string',
                                                            'enum' => ['ON', 'OFF'],
                                                            'example' => 'ON',
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates the status of the turnstile checker. Possible values: ON or OFF.',
                                                            // phpcs:enable
                                                        ],
                                                        'CONTACT_EMAIL' => [
                                                            'type' => 'string',
                                                            'example' => 'support@example.com',
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Contact email address for support or inquiries.',
                                                            // phpcs:enable
                                                        ],
                                                        'TOS' => [
                                                            'type' => 'string',
                                                            'enum' => ['LINK', 'TEXT_EDITOR'],
                                                            'example' => 'LINK',
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Defines the type of Terms of Service. Possible values: LINK or TEXT_EDITOR.',
                                                            // phpcs:enable
                                                        ],
                                                        'PRIVACY_POLICY' => [
                                                            'type' => 'string',
                                                            'enum' => ['LINK', 'TEXT_EDITOR'],
                                                            'example' => 'TEXT_EDITOR',
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Defines the type of Privacy Policy. Possible values: LINK or TEXT_EDITOR.',
                                                            // phpcs:enable
                                                        ],
                                                        'TWO_FACTOR_AUTH_STATUS' => [
                                                            'type' => 'string',
                                                            'enum' => [
                                                                'NOT_ENFORCED',
                                                                'ENFORCED_FOR_LOCAL',
                                                                'ENFORCED_FOR_ALL',
                                                            ],
                                                            'example' => 'NOT_ENFORCED',
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Status of two-factor authentication enforcement. Possible values: NOT_ENFORCED, ENFORCED_FOR_LOCAL, ENFORCED_FOR_ALL.',
                                                            // phpcs:enable
                                                        ],
                                                    ],
                                                ],
                                                'auth' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'TURNSTILE_CHECKER' => [
                                                            'type' => 'boolean',
                                                            'example' => true,
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates whether Turnstile validator is enabled (true) or disabled (false).',
                                                            // phpcs:enable
                                                        ],
                                                        'AUTH_METHOD_SAML_ENABLED' => [
                                                            'type' => 'boolean',
                                                            'example' => true,
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates whether SAML authentication is enabled (true) or disabled (false).',
                                                            // phpcs:enable
                                                        ],
                                                        'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => [
                                                            'type' => 'boolean',
                                                            'example' => true,
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates whether Google login is enabled (true) or disabled (false).',
                                                            // phpcs:enable
                                                        ],
                                                        'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED' => [
                                                            'type' => 'boolean',
                                                            'example' => true,
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates whether Microsoft login is enabled (true) or disabled (false).',
                                                            // phpcs:enable
                                                        ],
                                                        'AUTH_METHOD_REGISTER_ENABLED' => [
                                                            'type' => 'boolean',
                                                            'example' => true,
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates whether user registration is enabled (true) or disabled (false).',
                                                            // phpcs:enable
                                                        ],
                                                        'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' => [
                                                            'type' => 'boolean',
                                                            'example' => true,
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates whether traditional (username/password) login is enabled (true) or disabled (false).',
                                                            // phpcs:enable
                                                        ],
                                                        'AUTH_METHOD_SMS_REGISTER_ENABLED' => [
                                                            'type' => 'boolean',
                                                            'example' => true,
                                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                                            'description' => 'Indicates whether SMS-based registration is enabled (true) or disabled (false).',
                                                            // phpcs:enable
                                                        ],
                                                    ],
                                                ],
                                                'turnstile' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'TURNSTILE_KEY' => [
                                                            'type' => 'string',
                                                            'example' => 'example_turnstile_key',
                                                            'description' => 'The API key for the turnstile service.',
                                                        ],
                                                    ],
                                                ],
                                                'google' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'GOOGLE_CLIENT_ID' => [
                                                            'type' => 'string',
                                                            'example' => 'example_google_client_id',
                                                            'description' => 'The Google client ID 
                                                            used for authentication.',
                                                        ],
                                                    ],
                                                ],
                                                'microsoft' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'MICROSOFT_CLIENT_ID' => [
                                                            'type' => 'string',
                                                            'example' => 'example_microsoft_client_id',
                                                            'description' => 'The Microsoft client ID 
                                                            used for authentication.',
                                                        ],
                                                    ],
                                                ],
                                                'saml' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'SAML_IDP_ENTITY_ID' => [
                                                            'type' => 'string',
                                                            'example' => 'https://example.com/saml/metadata',
                                                            'description' => 'The SAML Identity Provider entity ID.',
                                                        ],
                                                        'SAML_IDP_SSO_URL' => [
                                                            'type' => 'string',
                                                            'example' => 'https://example.com/saml/sso',
                                                            'description' => 'The SAML Identity Provider 
                                                            Single Sign-On URL.',
                                                        ],
                                                        'SAML_IDP_X509_CERT' => [
                                                            'type' => 'string',
                                                            'example' => 'MIIC...AB',
                                                            'description' => 'The SAML Identity Provider 
                                                            X.509 certificate.',
                                                        ],
                                                        'SAML_SP_ENTITY_ID' => [
                                                            'type' => 'string',
                                                            'example' => 'https://example.com/saml/sp',
                                                            'description' => 'The SAML Service Provider entity ID.',
                                                        ],
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
                                            'PLATFORM_MODE' => 'Live',
                                            'USER_VERIFICATION' => true,
                                            'TURNSTILE_CHECKER' => true,
                                            'CONTACT_EMAIL' => 'support@example.com',
                                            'TOS' => 'LINK',
                                            'PRIVACY_POLICY' => 'LINK',
                                            'TWO_FACTOR_AUTH_STATUS' => 'NOT_ENFORCED'
                                        ],
                                        'auth' => [
                                            'AUTH_METHOD_SAML_ENABLED' => true,
                                            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => true,
                                            'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED' => true,
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
                                        'microsoft' => [
                                            'MICROSOFT_CLIENT_ID' => 'example_microsoft_client_id',
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
            name: 'app_v1_config_settings',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new GetCollection(
            uriTemplate: '/config/profile/android',
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
                                                        'eapType' => ['type' => 'integer'],
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
                                            'eapType' => 21,
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
                    The request should be sent as JSON with the RSA public_key included in the body.',
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
            name: 'api_v1_config_profile_android',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new GetCollection(
            uriTemplate: '/config/profile/ios',
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
                                                        'acceptEAPTypes' => ['type' => 'integer'],
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
                                            'acceptEAPTypes' => 21,
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
            The request should be sent as JSON with the RSA public_key included in the body.',
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
            name: 'api_v1_config_profile_ios',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
    ],
)]
class Setting
{
}
