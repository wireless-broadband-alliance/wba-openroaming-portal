<?php

namespace App\Api\V2\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Api\V1\Controller\AuthController;
use App\Api\V1\Controller\GetCurrentUserController;
use App\Api\V1\Controller\RegistrationController;
use App\Api\V1\Controller\TwoFAController;
use App\Repository\UserRepository;
use ArrayObject;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/user',
            controller: GetCurrentUserController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'User details retrieved successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'uuid' => ['type' => 'string'],
                                                'email' => ['type' => 'string'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                ],
                                                'first_name' => ['type' => 'string'],
                                                'last_name' => ['type' => 'string'],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => [
                                                                'type' => 'string',
                                                                'enum' => [
                                                                    'SAML Account',
                                                                    'Google Account',
                                                                    'Microsoft Account',
                                                                    'Portal Account',
                                                                ],
                                                                'example' => 'Google Account',
                                                                // phpcs:disable Generic.Files.LineLength.TooLong
                                                                'description' => 'The authentication provider for the user. Possible values: SAML Account, Google Account, Microsoft Account, Portal Account. If the provider is "Portal Account", the provider_id must be "Email" or "Phone Number".',
                                                                // phpcs:enable
                                                            ],
                                                            'provider_id' => [
                                                                'type' => 'string',
                                                                'enum' => [
                                                                    'Email',
                                                                    'Phone Number',
                                                                ],
                                                                'example' => 'Email',
                                                                // phpcs:disable Generic.Files.LineLength.TooLong
                                                                'description' => 'The unique identifier for the external authentication provider linked to the user. For "Google Account", this is an OAuth token. For "Portal Account", this must be either "Email" or "Phone Number".',
                                                                // phpcs:enable
                                                            ],
                                                        ],
                                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                                        'description' => "An array containing external authentication methods associated with the user. Each item specifies the provider and the unique provider ID. Special behavior: For Portal Account, the provider_id is restricted to the values 'Email' or 'Phone Number'.",
                                                        // phpcs:enable
                                                    ],
                                                ],
                                                'phone_number' => ['type' => 'string', 'nullable' => true],
                                                'is_verified' => ['type' => 'boolean'],
                                                'created_at' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                ],
                                                'forgot_password_request' => ['type' => 'boolean'],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        'uuid' => 'user@example.com',
                                        'email' => 'user@example.com',
                                        'roles' => ['ROLE_USER'],
                                        'first_name' => 'John',
                                        'last_name' => 'Doe',
                                        'user_external_auths' => [
                                            [
                                                'provider' => 'Portal Account',
                                                'provider_id' => 'Email || Phone Number',
                                            ],
                                        ],
                                        'phone_number' => null,
                                        'is_verified' => true,
                                        'created_at' => "0000-00-00T00:00:00+00:00",
                                        'forgot_password_request' => false,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    401 => [
                        'description' => 'Access token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'error' => ['type' => 'string'],
                                    ],
                                ],
                                'examples' => [
                                    'jwt_not_found' => [
                                        'summary' => 'JWT Token not found',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'JWT Token not found!',
                                        ],
                                    ],
                                    'jwt_invalid' => [
                                        'summary' => 'JWT Token invalid',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'JWT Token is invalid!',
                                        ],
                                    ],
                                    'jwt_expired' => [
                                        'summary' => 'JWT Token expired',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'JWT Token is expired!',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Unauthorized Access - Account unverified/banned',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'error' => ['type' => 'string'],
                                    ],
                                ],
                                'examples' => [
                                    'unauthorized_access' => [
                                        'summary' => 'Unauthorized Access',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Unauthorized - You do not have permission to access this resource.',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'invalid_verification' => [
                                        'summary' => 'User account is not verified',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is not verified!',
                                        ],
                                    ],
                                    'banned_account' => [
                                        'summary' => 'User account is banned',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is banned from the system!',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Retrieve current authenticated user',
                description: 'This endpoint returns the details of the currently authenticated user.',
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
                security: [
                    [
                        'bearerAuth' => [],
                    ]
                ],
            ),
            shortName: 'User',
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            securityMessage: 'Sorry, but you don\'t have permission to access this resource.',
            name: 'api_v2_get_current_user',
        ),
        new Post(
            uriTemplate: '/twoFA/request',
            controller: TwoFAController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Requested two-factor authentication token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'message' => [
                                                    'type' => 'string',
                                                    'example' => 'Two-Factor authentication code successfully sent.' .
                                                        ' You have X attempts remaining to request a new one.',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid request data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Error message explaining why the request failed',
                                            'example' => 'Missing required fields or invalid data',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'captcha_failed' => [
                                        'summary' => 'CAPTCHA validation failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'CAPTCHA validation failed',
                                        ],
                                    ],
                                    'missing_fields' => [
                                        'summary' => 'Missing fields',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Missing required fields: uuid, password or turnstile_token',
                                        ],
                                    ],
                                    'missing_2fa_setting' => [
                                        'summary' => 'Missing 2FA setting',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Missing required configuration setting: TWO_FACTOR_AUTH_RESEND_INTERVAL TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'invalid_json' => [
                                        'summary' => 'Invalid json format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid json format',
                                        ],
                                    ],
                                    'miss_typed_uuid' => [
                                        'summary' => 'Invalid Account Uuid',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid credentials'
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    401 => [
                        'description' => 'Invalid credentials.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Invalid credentials.',
                                            'description' => 'Invalid credentials provided'
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'missing_user_account' => [
                                        'summary' => 'Invalid Credentials',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid credentials'
                                        ],
                                    ],
                                    'miss_typed_password' => [
                                        'summary' => 'Invalid Password',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid credentials'
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Account Type',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'example' => 'Unauthorized - You do not have permission to access this resource.',
                                            // phpcs:enable
                                            'description' => 'Details of the authentication failure',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'invalid_verification' => [
                                        'summary' => 'User account is not verified',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is not verified!',
                                        ],
                                    ],
                                    'banned_account' => [
                                        'summary' => 'User account is banned',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is banned from the system!',
                                        ],
                                    ],
                                    'invalid_account_type' => [
                                        'summary' => 'User account invalid',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid account type.' .
                                                ' Please only use email/phone number accounts from the portal',
                                        ],
                                    ],
                                    'invalid_2fa_configuration' => [
                                        'summary' => 'Invalid 2FA configuration',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid Two-Factor Authentication configuration Please ensure' .
                                                'that 2FA is set up using either email or SMS for this account',
                                        ],
                                    ],
                                    'invalid_2fa_uncompleted_configuration' => [
                                        'summary' => 'The Two-Factor Authentication configuration is incompleted.',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'The Two-Factor Authentication (2FA) configuration is' .
                                                ' incomplete. Please set up 2FA using either email or SMS',
                                        ],
                                    ],
                                    'password_reset_request_active' => [
                                        'summary' => 'Forgot password request active',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Your request cannot be processed at this time due to a pending action. If your account is active, re-login to complete the action',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    429 => [
                        'description' => 'Too many requests.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Too many requests.',
                                            'description' => 'Too many requests provided'
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'waiting_interval_between_requests' => [
                                        'summary' => 'Interval of waiting between requests',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'You need to wait %d seconds before asking for a new code.'
                                        ],
                                    ],
                                    'limit_of_request_exceeded' => [
                                        'summary' => 'Limit of request exceeded',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Too many attempts. You have exceeded the limit of %d' .
                                                ' attempts. Please wait %d minutes before trying again.',
                                        ],
                                    ],
                                    'validation_attempts' => [
                                        'summary' => 'Validation attempts exceeded',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Too many validation attempts. You have exceeded the' .
                                                ' limit of %d attempts. Please wait %d minute(s) before trying again.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Two Factor Authentication request Status',
                description: 'This endpoint provides Two-Factor Authentication code only for portal accounts. 
                To be able to request a authentication code the account needs to have setup a 2fa with email or SMS.',
                requestBody: new RequestBody(
                    description: 'User Two Factor Authentication request status',
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uuid' => [
                                        'type' => 'string',
                                        'description' => 'Unique identifier of the user',
                                        'example' => 'user-uuid-example'
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'description' => 'Password of the user',
                                        'example' => 'user-password-example'
                                    ],
                                    'turnstile_token' => [
                                        'type' => 'string',
                                        'description' => 'CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['uuid', 'password', 'turnstile_token'],
                            ],
                        ],
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth',
            name: 'api_v2_twoFA_request',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v2/auth/local',
            controller: AuthController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Authenticated user details and JWT token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'uuid' => [
                                                    'type' => 'string',
                                                    'example' => 'user@example.com',
                                                    'description' => 'UUID of the authenticated user'
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'example' => 'user@example.com',
                                                    'description' => 'Email of the authenticated user'
                                                ],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_USER'],
                                                    'description' => 'Roles assigned to the user'
                                                ],
                                                'first_name' => [
                                                    'type' => 'string',
                                                    'example' => 'John',
                                                    'description' => 'First name of the user'
                                                ],
                                                'last_name' => [
                                                    'type' => 'string',
                                                    'example' => 'Doe',
                                                    'description' => 'Last name of the user'
                                                ],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => [
                                                                'type' => 'string',
                                                                'example' => 'Portal Account',
                                                                'description' => 'Authentication provider'
                                                            ],
                                                            'provider_id' => [
                                                                'type' => 'string',
                                                                'example' => 'Email',
                                                                'description' => 'Provider identifier'
                                                            ],
                                                        ],
                                                    ],
                                                    'description' => 'External authentication details'
                                                ],
                                                'token' => [
                                                    'type' => 'string',
                                                    'example' => 'jwt_token',
                                                    'description' => 'JWT token for the authenticated session'
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid request data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Error message explaining why the request failed',
                                            'example' => 'Missing required fields or invalid data',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'captcha_failed' => [
                                        'summary' => 'CAPTCHA validation failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'CAPTCHA validation failed',
                                        ],
                                    ],
                                    'missing_fields' => [
                                        'summary' => 'Missing fields',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Missing required fields: uuid, password or turnstile_token',
                                        ],
                                    ],
                                    'missing_2fa_setting' => [
                                        'summary' => 'Missing 2FA settings',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Missing required configuration setting: TWO_FACTOR_AUTH_STATUS',
                                        ],
                                    ],
                                    'invalid_json' => [
                                        'summary' => 'Invalid json format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid json format',
                                        ],
                                    ],
                                    'invalid_user' => [
                                        'summary' => 'Invalid user',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid user provided. Please verify the user data',
                                        ]
                                    ]
                                ],
                            ],
                        ],
                    ],
                    401 => [
                        'description' => 'Invalid credentials.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Invalid credentials.',
                                            'description' => 'Invalid credentials provided'
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    '2fa_not_configured' => [
                                        'summary' => '2FA Not Configured',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.'
                                            // phpcs:enable
                                        ],
                                    ],
                                    '2fa_enforced_failed' => [
                                        'summary' => '2FA Enforced Failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                                        ],
                                    ],
                                    '2fa_configuration_failed' => [
                                        'summary' => '2FA Configuration Failed',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'missing_user_account' => [
                                        'summary' => 'Invalid Credentials',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid credentials'
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Account unverified/banned',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'example' => 'Unauthorized - You do not have permission to access this resource.',
                                            // phpcs:enable
                                            'description' => 'Details of the authentication failure',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'invalid_verification' => [
                                        'summary' => 'User account is not verified',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is not verified!',
                                        ],
                                    ],
                                    'banned_account' => [
                                        'summary' => 'User account is banned',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is banned from the system!',
                                        ],
                                    ],
                                    'password_reset_request_active' => [
                                        'summary' => 'Forgot password request active',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Your request cannot be processed at this time due to a pending action. If your account is active, re-login to complete the action',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error due to internal issues.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'An error occurred: Generic server-side error.',
                                        ],
                                    ],
                                    'required' => ['success', 'message'],
                                ],
                                'examples' => [
                                    'server_issue' => [
                                        'summary' => 'Example of a general server error',
                                        'value' => [
                                            'success' => false,
                                            'message' => 'An error occurred: Generic server-side error.',
                                        ],
                                    ],
                                    'missing_jwt_keys' => [
                                        'summary' => 'Example of missing JWT keys error',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'message' => 'JWT key files are missing. Please ensure both private and public keys exist.',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Authenticate a user locally',
                description: 'This endpoint authenticates a user using their UUID, password, and a CAPTCHA token.
                Platform can require the authentication with Two-Factor, the twoFACode parameter will be asked 
                based on the TWO_FACTOR_AUTH_STATUS setting.',
                requestBody: new RequestBody(
                    description: 'User credentials and CAPTCHA validation token',
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uuid' => [
                                        'type' => 'string',
                                        'description' => 'Unique identifier of the user',
                                        'example' => 'user-uuid-example'
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'description' => 'Password of the user',
                                        'example' => 'user-password-example'
                                    ],
                                    'turnstile_token' => [
                                        'type' => 'string',
                                        'description' => 'CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                    'twoFACode' => [
                                        'type' => 'string',
                                        'description' => 'Code for 2FA validation',
                                        'example' => '02YZR88R'
                                    ],
                                ],
                                'required' => ['uuid', 'password', 'turnstile_token', 'twoFACode'],
                            ],
                        ],
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth',
            name: 'api_v2_auth_local',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v2/auth/saml',
            controller: AuthController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Authenticated user details and JWT token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'uuid' => [
                                                    'type' => 'string',
                                                    'description' => 'User UUID',
                                                    'example' => 'user-uuid-example',
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'description' => 'User email address',
                                                    'example' => 'user@example.com',
                                                ],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'description' => 'List of user roles',
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => [
                                                    'type' => 'string',
                                                    'description' => 'User first name',
                                                    'example' => 'John',
                                                ],
                                                'last_name' => [
                                                    'type' => 'string',
                                                    'description' => 'User last name',
                                                    'example' => 'Doe',
                                                ],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => [
                                                                'type' => 'string',
                                                                'example' => 'SAML Account',
                                                                'description' => 'Authentication provider',
                                                            ],
                                                            'provider_id' => [
                                                                'type' => 'string',
                                                                'example' => 'saml_account_name',
                                                                'description' => 'Provider identifier',
                                                            ],
                                                        ],
                                                    ],
                                                    'description' => 'External authentication details',
                                                ],
                                                'token' => [
                                                    'type' => 'string',
                                                    'description' => 'JWT token for the authenticated user',
                                                    'example' => 'jwt-token-example',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Bad Request due to missing or invalid SAML response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Error message explaining why the request failed',
                                            'example' => 'SAML Response not found',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'saml_response_not_found' => [
                                        'summary' => 'SAML Response not found',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'SAML Response not found',
                                        ],
                                    ],
                                    'invalid_user' => [
                                        'summary' => 'Invalid user',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid user provided. Please verify the user data',
                                        ]
                                    ]
                                ],
                            ],
                        ],
                    ],
                    401 => [
                        'description' => 'Unauthorized due to invalid SAML assertion.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Error message for why authentication failed',
                                            'example' => 'Invalid SAML Assertion',
                                        ],
                                    ],
                                    'examples' => [
                                        'invalid_saml_assertion' => [
                                            'summary' => 'Unable to validate SAML assertion',
                                            'value' => [
                                                'success' => false,
                                                'error' => 'Unable to validate SAML assertion',
                                            ],
                                        ],
                                        'authentication_failed' => [
                                            'summary' => 'Authentication Failed',
                                            'value' => [
                                                'success' => false,
                                                'error' => 'Authentication Failed',
                                            ],
                                        ],
                                        'examples' => [
                                            '2fa_not_configured' => [
                                                'summary' => '2FA Not Configured',
                                                'value' => [
                                                    'success' => false,
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'error' => 'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.'
                                                    // phpcs:enable
                                                ],
                                            ],
                                            '2fa_enforced_failed' => [
                                                'summary' => '2FA Enforced Failed',
                                                'value' => [
                                                    'success' => false,
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'error' => 'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                                                    // phpcs:enable
                                                ],
                                            ],
                                            '2fa_configuration_failed' => [
                                                'summary' => '2FA Configuration Failed',
                                                'value' => [
                                                    'success' => false,
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'error' => 'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
                                                    // phpcs:enable
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Access Forbidden - The request was made but the server denies permission.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Details about why access was forbidden.',
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'example' => 'Unauthorized - You do not have permission to access this resource.',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'invalid_saml_response_idp_entity' => [
                                        'summary' => 'Invalid IDP Entity',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'The provided IDP Entity is invalid or does not match the expected configuration.',
                                            // phpcs:enabl
                                        ],
                                    ],
                                    'invalid_saml_response_certificate' => [
                                        'summary' => 'Invalid Certificate',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'The provided certificate is invalid or does not match the expected configuration.',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'invalid_verification' => [
                                        'summary' => 'User account is not verified',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is not verified!',
                                        ],
                                    ],
                                    'banned_account' => [
                                        'summary' => 'User account is banned',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is banned from the system!',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error due to internal issues.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'An error occurred: Generic server-side error.',
                                        ],
                                    ],
                                    'required' => ['success', 'message'],
                                ],
                                'examples' => [
                                    'server_issue' => [
                                        'summary' => 'Example of a general server error',
                                        'value' => [
                                            'success' => false,
                                            'message' => 'An error occurred: Generic server-side error.',
                                        ],
                                    ],
                                    'missing_jwt_keys' => [
                                        'summary' => 'Example of missing JWT keys error',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'message' => 'JWT key files are missing. Please ensure both private and public keys exist.',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Authenticate a user via SAML',
                description: 'This endpoint authenticates a user using their SAML response. 
                If the user is not found in the database, a new user will be created based on the SAML assertion. 
                The response includes user details along with a JWT token if authentication is successful.
                Also if the platform requires authentication with Two-Factor, the twoFACode parameter will be asked 
                based on the TWO_FACTOR_AUTH_STATUS setting.',
                requestBody: new RequestBody(
                    description: 'SAML response required for user authentication. 
                    The request should be sent as `multipart/form-data` with the SAML response included
                     as a form field (not a file).',
                    content: new ArrayObject([
                        'multipart/form-data' => new ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'SAMLResponse' => [
                                        'type' => 'string',
                                        'description' => 'Base64-encoded SAML response included in the form data',
                                        'example' => 'base64-encoded-saml-assertion',
                                    ],
                                    'twoFACode' => [
                                        'type' => 'string',
                                        'description' => '6-7 digits code (2fa authentication or recovery codes)',
                                        'example' => '02YZR88R',
                                    ],
                                ],
                                'required' => ['SAMLResponse', 'twoFACode'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth',
            name: 'api_v2_auth_saml',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v2/auth/google',
            controller: AuthController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Authenticated user details and JWT token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'uuid' => [
                                                    'type' => 'string',
                                                    'example' => 'user-uuid-example',
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'example' => 'john_doe@example.com',
                                                ],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'string',
                                                    ],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => [
                                                    'type' => 'string',
                                                    'example' => 'John',
                                                ],
                                                'last_name' => [
                                                    'type' => 'string',
                                                    'example' => 'Doe',
                                                ],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => [
                                                                'type' => 'string',
                                                                'example' => 'Google Account',
                                                            ],
                                                            'provider_id' => [
                                                                'type' => 'string',
                                                                'example' => 'google_id_example',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                'token' => [
                                                    'type' => 'string',
                                                    'example' => 'jwt-token-example',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid request data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Missing authorization code!',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'invalid_json' => [
                                        'summary' => 'Invalid JSON format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid JSON format',
                                        ],
                                    ],
                                    'missing_authorization_code' => [
                                        'summary' => 'Missing authorization code',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Missing authorization code!',
                                        ],
                                    ],
                                    'email_not_allowed' => [
                                        'summary' => 'Email not allowed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'This code is not associated with a google account!',
                                        ],
                                    ],
                                    'invalid_user' => [
                                        'summary' => 'Invalid user',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid user provided. Please verify the user data',
                                        ]
                                    ]
                                ],
                            ],
                        ],
                    ],
                    401 => [
                        'description' => 'Invalid credentials.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Invalid credentials.',
                                            'description' => 'Invalid credentials provided'
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    '2fa_not_configured' => [
                                        'summary' => '2FA Not Configured',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.'
                                            // phpcs:enable
                                        ],
                                    ],
                                    '2fa_enforced_failed' => [
                                        'summary' => '2FA Enforced Failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                                        ],
                                    ],
                                    '2fa_configuration_failed' => [
                                        'summary' => '2FA Configuration Failed',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Account unverified/banned',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'example' => 'Unauthorized - You do not have permission to access this resource.',
                                            // phpcs:enable
                                            'description' => 'Details of the authentication failure',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'invalid_verification' => [
                                        'summary' => 'User account is not verified',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is not verified!',
                                        ],
                                    ],
                                    'banned_account' => [
                                        'summary' => 'User account is banned',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is banned from the system!',
                                        ],
                                    ],
                                    'email_domain_not_allowed' => [
                                        'summary' => 'User email domain is not allowed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'our email domain is not allowed to use this platform!',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error due to internal issues.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'An error occurred: Generic server-side error.',
                                        ],
                                    ],
                                    'required' => ['success', 'message'],
                                ],
                                'examples' => [
                                    'server_issue' => [
                                        'summary' => 'Example of a general server error',
                                        'value' => [
                                            'success' => false,
                                            'message' => 'An error occurred: Generic server-side error.',
                                        ],
                                    ],
                                    'missing_jwt_keys' => [
                                        'summary' => 'Example of missing JWT keys error',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'message' => 'JWT key files are missing. Please ensure both private and public keys exist.',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Authenticate a user via Google',
                description: 'This endpoint authenticates a user using their Google account. 
                A valid Google OAuth authorization code is required. 
                If the user is successfully authenticated, user details and a JWT token will be returned.
                Also if the platform requires authentication with Two-Factor, the twoFACode parameter will be asked 
                based on the TWO_FACTOR_AUTH_STATUS setting.',
                requestBody: new RequestBody(
                    description: 'Google authorization code required for user authentication.
                     The request should be sent as JSON with the authorization code included in the body.',
                    content: new ArrayObject([
                        'application/json' => new ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => [
                                        'type' => 'string',
                                        'description' => 'The Google OAuth authorization code',
                                        'example' => '4/0AdKgLCxjQ74mKAg9vs_f7PuO99DR',
                                    ],
                                    'twoFACode' => [
                                        'type' => 'string',
                                        'description' => '6-7 digits code (2fa authentication or recovery codes)',
                                        'example' => '02YZR88R',
                                    ],
                                ],
                                'required' => ['code', 'twoFACode'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth',
            name: 'api_v2_auth_google',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v2/auth/microsoft',
            controller: AuthController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Authenticated user details and JWT token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'uuid' => [
                                                    'type' => 'string',
                                                    'example' => 'user-uuid-example',
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'example' => 'john_doe@example.com',
                                                ],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'string',
                                                    ],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => [
                                                    'type' => 'string',
                                                    'example' => 'John',
                                                ],
                                                'last_name' => [
                                                    'type' => 'string',
                                                    'example' => 'Doe',
                                                ],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => [
                                                                'type' => 'string',
                                                                'example' => 'Microsoft Account',
                                                            ],
                                                            'provider_id' => [
                                                                'type' => 'string',
                                                                'example' => 'microsoft_id_example',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                'token' => [
                                                    'type' => 'string',
                                                    'example' => 'jwt-token-example',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid request data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Missing authorization code!',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'invalid_json' => [
                                        'summary' => 'Invalid JSON format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid JSON format',
                                        ],
                                    ],
                                    'missing_authorization_code' => [
                                        'summary' => 'Missing authorization code',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Missing authorization code!',
                                        ],
                                    ],
                                    'email_not_allowed' => [
                                        'summary' => 'Email not allowed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'This code is not associated with a microsoft account!',
                                        ],
                                    ],
                                    'invalid_user' => [
                                        'summary' => 'Invalid user',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid user provided. Please verify the user data',
                                        ]
                                    ]
                                ],
                            ],
                        ],
                    ],
                    401 => [
                        'description' => 'Invalid credentials.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Invalid credentials.',
                                            'description' => 'Invalid credentials provided'
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    '2fa_not_configured' => [
                                        'summary' => '2FA Not Configured',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.'
                                            // phpcs:enable
                                        ],
                                    ],
                                    '2fa_enforced_failed' => [
                                        'summary' => '2FA Enforced Failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                                        ],
                                    ],
                                    '2fa_configuration_failed' => [
                                        'summary' => '2FA Configuration Failed',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Account unverified/banned',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => [
                                            'type' => 'string',
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'example' => 'Unauthorized - You do not have permission to access this resource.',
                                            // phpcs:enable
                                            'description' => 'Details of the authentication failure',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'invalid_verification' => [
                                        'summary' => 'User account is not verified',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is not verified!',
                                        ],
                                    ],
                                    'banned_account' => [
                                        'summary' => 'User account is banned',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User account is banned from the system!',
                                        ],
                                    ],
                                    'email_domain_not_allowed' => [
                                        'summary' => 'User email domain is not allowed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'our email domain is not allowed to use this platform!',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error due to internal issues.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'An error occurred: Generic server-side error.',
                                        ],
                                    ],
                                    'required' => ['success', 'message'],
                                ],
                                'examples' => [
                                    'server_issue' => [
                                        'summary' => 'Example of a general server error',
                                        'value' => [
                                            'success' => false,
                                            'message' => 'An error occurred: Generic server-side error.',
                                        ],
                                    ],
                                    'missing_jwt_keys' => [
                                        'summary' => 'Example of missing JWT keys error',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'message' => 'JWT key files are missing. Please ensure both private and public keys exist.',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Authenticate a user via Microsoft',
                description: 'This endpoint authenticates a user using their Microsoft account. 
                A valid Microsoft OAuth authorization code is required. 
                If the user is successfully authenticated, user details and a JWT token will be returned.
                Also if the platform requires authentication with Two-Factor, the twoFACode parameter will be asked 
                based on the TWO_FACTOR_AUTH_STATUS setting.',
                requestBody: new RequestBody(
                    description: 'Microsoft authorization code required for user authentication.
                     The request should be sent as JSON with the authorization code included in the body.',
                    content: new ArrayObject([
                        'application/json' => new ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => [
                                        'type' => 'string',
                                        'description' => 'The Microsoft OAuth authorization code',
                                        'example' => '0.AQk6Lf2I2XGhQkWlU8gBp0KmxeNn2KTcbsJh.8Qt3OeYCB4sQ2FHo',
                                    ],
                                    'twoFACode' => [
                                        'type' => 'string',
                                        'description' => '6-7 digits code (2fa authentication or recovery codes)',
                                        'example' => '02YZR88R',
                                    ],
                                ],
                                'required' => ['code', 'twoFACode'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth',
            name: 'api_v2_auth_microsoft',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v2/auth/local/register',
            controller: RegistrationController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'User registered successfully',
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
                                                'message' => [
                                                    'type' => 'string',
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'example' => 'Registration successful. Please check your email for further instructions.',
                                                    // phpcs:enable
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'message' => 'Registration successful. Please check your email for further instructions.',
                                        // phpcs:enable
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid request data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Error message for invalid data',
                                            'example' => 'Missing required fields: email, password or turnstile_token',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'captcha_failed' => [
                                        'summary' => 'CAPTCHA validation failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'CAPTCHA validation failed',
                                        ],
                                    ],
                                    'missing_fields' => [
                                        'summary' => 'Missing Fields',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Missing required fields: email, password or turnstile_token',
                                        ],
                                    ],
                                    'invalid_email_format' => [
                                        'summary' => 'Invalid email format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid email format',
                                        ],
                                    ],
                                    'invalid_json' => [
                                        'summary' => 'Invalid JSON format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid JSON format',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Register a new user via local authentication',
                description: 'This endpoint registers a new user using their email and password, with 
                CAPTCHA validation via the Turnstile token. It handles user creation, password hashing,
                 and CAPTCHA verification. If the user already exists, it returns a conflict error.',
                requestBody: new RequestBody(
                    description: 'User registration data and CAPTCHA validation token. 
                    The request should include the user\'s email, password, and Turnstile CAPTCHA token.',
                    content: new ArrayObject([
                        'application/json' => new ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'example' => 'user@example.com',
                                        'description' => 'User email address',
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'example' => 'strongpassword',
                                        'description' => 'User password',
                                    ],
                                    'first_name' => [
                                        'type' => 'string',
                                        'example' => 'John',
                                        'description' => 'First name of the user',
                                    ],
                                    'last_name' => [
                                        'type' => 'string',
                                        'example' => 'Doe',
                                        'description' => 'Last name of the user',
                                    ],
                                    'turnstile_token' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token from Turnstile',
                                        'example' => 'valid_test_token',
                                    ],
                                ],
                                'required' => ['email', 'password', 'turnstile_token'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth Register',
            name: 'api_v2_auth_local_register',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v2/auth/sms/register',
            controller: RegistrationController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'User registered successfully',
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
                                                'message' => [
                                                    'type' => 'string',
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'example' => 'SMS User Account Registered Successfully. A verification code has been sent to your phone.',
                                                    // phpcs:enable
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'message' => 'SMS User Account Registered Successfully. A verification code has been sent to your phone.',
                                        // phpcs:enable
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid request data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Error message explaining why the request failed',
                                            'example' => 'Missing required fields or invalid data',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'captcha_failed' => [
                                        'summary' => 'CAPTCHA validation failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'CAPTCHA validation failed',
                                        ],
                                    ],
                                    'missing_fields' => [
                                        'summary' => 'Missing Fields',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Missing required fields: country code, phone number, password, or turnstile_token',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'invalid_phone_number_format_or_country_code' => [
                                        'summary' => 'Invalid phone number format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid phone number format or country code.',
                                        ],
                                    ],
                                    'invalid_json' => [
                                        'summary' => 'Invalid json format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid json format',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error while processing the request',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Failed to send SMS',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'failed_send_sms' => [
                                        'summary' => 'Failed to send SMS',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Failed to send SMS',
                                        ],
                                    ],
                                    'fallback_sms' => [
                                        'summary' => 'SMS sending failed after user registration',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'User registered but SMS could not be sent.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Register a new user via SMS authentication',
                description: 'This endpoint registers a new user using their phone number and 
                validates the request with a CAPTCHA token.',
                requestBody: new RequestBody(
                    description: 'User registration data with SMS and CAPTCHA validation token',
                    content: new ArrayObject([
                        'application/json' => new ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'country_code' => [
                                        'type' => 'string',
                                        'example' => 'PT',
                                        'description' => 'User phone number',
                                    ],
                                    'phone_number' => [
                                        'type' => 'string',
                                        'example' => '1234567890',
                                        'description' => 'User phone number',
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'example' => 'strongpassword',
                                        'description' => 'User password',
                                    ],
                                    'first_name' => [
                                        'type' => 'string',
                                        'example' => 'John',
                                        'description' => 'First name of the user',
                                    ],
                                    'last_name' => [
                                        'type' => 'string',
                                        'example' => 'Doe',
                                        'description' => 'Last name of the user',
                                    ],
                                    'turnstile_token' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token',
                                    ],
                                ],
                                'required' => ['country_code', 'phone_number', 'password', 'turnstile_token'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth Register',
            name: 'api_v2_auth_sms_register',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v2/auth/local/reset',
            controller: AuthController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Password reset email sent successfully',
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
                                                'message' => [
                                                    'type' => 'string',
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'example' => 'If the email exists, we have sent you a new one to: user@example.com',
                                                    // phpcs:enable
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'message' => 'If the email exists, we have sent you a new one to: user@example.com',
                                        // phpcs:enable
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid request data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Error message explaining why the request failed',
                                            'example' => 'Missing required fields or invalid data',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'captcha_failed' => [
                                        'summary' => 'CAPTCHA validation failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'CAPTCHA validation failed',
                                        ],
                                    ],
                                    'missing_fields' => [
                                        'summary' => 'Missing Fields',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Missing required fields: email or turnstile_token',
                                        ],
                                    ],
                                    'invalid_email_format' => [
                                        'summary' => 'Invalid email format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid email format',
                                        ],
                                    ],
                                    'invalid_json' => [
                                        'summary' => 'Invalid JSON format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid JSON format',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Trigger a password reset for a local auth account',
                description: 'This endpoint triggers a password reset for a local auth account. 
                The user must provide their email and a CAPTCHA validation token. 
                The endpoint verifies if the user has an external auth with "PortalAccount" and "EMAIL" providerId,
                 then proceeds with the password reset if the conditions are met.',
                requestBody: new RequestBody(
                    description: 'Password reset request data, including CAPTCHA validation token and user email',
                    content: new ArrayObject([
                        'application/json' => new ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'description' => 'The email of the user requesting the password reset',
                                        'example' => 'user@example.com',
                                    ],
                                    'turnstile_token' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token',
                                    ],
                                ],
                                'required' => ['email', 'turnstile_token'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth Reset',
            name: 'api_v2_auth_local_reset',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v2/auth/sms/reset',
            controller: AuthController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'Successfully sent the SMS with a new password and verification code',
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
                                                'message' => [
                                                    'type' => 'string',
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'example' => 'If the phone number exists, we have sent a new code to: +1234567890. You have %d attempt(s) left.',
                                                    // phpcs:enable
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'message' => 'If the phone number exists, we have sent a new code to: +1234567890. You have %d attempt(s) left.',
                                        // phpcs:enable
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Invalid request data',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Missing required fields or invalid data',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'captcha_failed' => [
                                        'summary' => 'CAPTCHA validation failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'CAPTCHA validation failed',
                                        ],
                                    ],
                                    'missing_fields' => [
                                        'summary' => 'Missing Fields',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Missing required fields: country code, phone number, turnstile_token',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'invalid_phone_number_format_or_country_code' => [
                                        'summary' => 'Invalid phone number format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid phone number format or country code.',
                                        ],
                                    ],
                                    'invalid_json' => [
                                        'summary' => 'Invalid JSON format',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid JSON format',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error while processing the request.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'description' => 'Error message explaining why the server error occurred',
                                            'example' => 'An unexpected error occurred while processing the request',
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'error' => 'An unexpected error occurred while processing the request',
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Trigger a password reset for an SMS auth account',
                description: 'This endpoint sends an SMS with a new password and verification code 
                if the user has a valid PortalAccount and has not exceeded SMS request limits. The endpoint also 
                enforces the time interval between requests and limits the number of attempts allowed.',
                requestBody: new RequestBody(
                    description: 'Password reset request data including CAPTCHA token and user phone number',
                    content: new ArrayObject([
                        'application/json' => new ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'country_code' => [
                                        'type' => 'string',
                                        'example' => 'PT',
                                        'description' => 'User phone number',
                                    ],
                                    'phone_number' => [
                                        'type' => 'string',
                                        'description' => 'The phone number of the user requesting password reset',
                                        'example' => '1234567890',
                                    ],
                                    'turnstile_token' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token',
                                    ],
                                ],
                                'required' => ['country_code', 'phone_number', 'turnstile_token'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth Reset',
            name: 'api_v2_auth_sms_reset',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
    ],
    extraProperties: ['api_version' => 'v2'],
)]
class User
{
}
