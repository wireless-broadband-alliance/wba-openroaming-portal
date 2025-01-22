<?php

namespace App\Entity;

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
use App\Repository\UserRepository;
use App\Security\CustomSamlUserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ApiResource(
    description: "The User entity returns values related to a user.",
    operations: [
        new GetCollection(
            uriTemplate: '/v1/user',
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
                                                            'provider' => ['type' => 'string'],
                                                            'provider_id' => ['type' => 'string'],
                                                        ],
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
            name: 'api_get_current_user',
        ),
        new Post(
            uriTemplate: '/v1/auth/local',
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
                                    'example' => [
                                        'success' => false,
                                        'error' => 'Invalid credentials',
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
                ],
                summary: 'Authenticate a user locally',
                description: 'This endpoint authenticates a user using their UUID, password, and a CAPTCHA token.',
                requestBody: new RequestBody(
                    description: 'User credentials and CAPTCHA validation token',
                    content: new \ArrayObject([
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
            name: 'api_auth_local',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v1/auth/saml',
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
                        'description' => 'Bad Request due to missing SAML response',
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
                                    'examples' => [
                                        'saml_response_not_found' => [
                                            'summary' => 'SAML Response not found',
                                            'value' => [
                                                'success' => false,
                                                'error' => 'SAML Response not found',
                                            ],
                                        ],
                                    ],
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
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'example' => 'Unauthorized - You do not have permission to access this resource.',
                                            // phpcs:enable
                                            'description' => 'Details of the authentication failure',
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
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error while processing the SAML response',
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
                                            'description' => 'Error message for why the server error occurred',
                                            'example' => 'SAML processing error',
                                        ],
                                    ],
                                    'examples' => [
                                        'saml_processing_error' => [
                                            'summary' => 'SAML processing error',
                                            'value' => [
                                                'success' => false,
                                                'error' => 'SAML processing error',
                                            ],
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
                The response includes user details along with a JWT token if authentication is successful.',
                requestBody: new RequestBody(
                    description: 'SAML response required for user authentication. 
                    The request should be sent as `multipart/form-data` with the SAML response included
                     as a form field (not a file).',
                    content: new \ArrayObject([
                        'multipart/form-data' => new \ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'SAMLResponse' => [
                                        'type' => 'string',
                                        'description' => 'Base64-encoded SAML response included in the form data',
                                        'example' => 'base64-encoded-saml-assertion',
                                    ],
                                ],
                                'required' => ['SAMLResponse'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth',
            name: 'api_auth_saml',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v1/auth/google',
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
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error due to internal issues or Google API failure',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'An error occurred.',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'Authentication_failed' => [
                                        'success' => false,
                                        'message' => 'Authentication Failed.',
                                    ],
                                    'Server_related' => [
                                        'success' => false,
                                        'message' => 'An error occurred: Generic server related error.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Authenticate a user via Google',
                description: 'This endpoint authenticates a user using their Google account. 
                A valid Google OAuth authorization code is required. 
                If the user is successfully authenticated, user details and a JWT token will be returned.',
                requestBody: new RequestBody(
                    description: 'Google authorization code required for user authentication.
                     The request should be sent as JSON with the authorization code included in the body.',
                    content: new \ArrayObject([
                        'application/json' => new \ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => [
                                        'type' => 'string',
                                        'description' => 'The Google OAuth authorization code',
                                        'example' => '4/0AdKgLCxjQ74mKAg9vs_f7PuO99DR',
                                    ],
                                ],
                                'required' => ['code'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth',
            name: 'api_auth_google',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v1/auth/microsoft',
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
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Server error due to internal issues or Microsoft API failure',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'An error occurred.',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'Authentication_failed' => [
                                        'success' => false,
                                        'message' => 'Authentication Failed.',
                                    ],
                                    'Server_related' => [
                                        'success' => false,
                                        'message' => 'An error occurred: Generic server related error.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Authenticate a user via Microsoft',
                description: 'This endpoint authenticates a user using their Microsoft account. 
                A valid Microsoft OAuth authorization code is required. 
                If the user is successfully authenticated, user details and a JWT token will be returned.',
                requestBody: new RequestBody(
                    description: 'Microsoft authorization code required for user authentication.
                     The request should be sent as JSON with the authorization code included in the body.',
                    content: new \ArrayObject([
                        'application/json' => new \ArrayObject([
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => [
                                        'type' => 'string',
                                        'description' => 'The Microsoft OAuth authorization code',
                                        'example' => '0.AQk6Lf2I2XGhQkWlU8gBp0KmxeNn2KTcbsJh.8Qt3OeYCB4sQ2FHo',
                                    ],
                                ],
                                'required' => ['code'],
                            ],
                        ]),
                    ]),
                    required: true,
                ),
                security: [],
            ),
            shortName: 'User Auth',
            name: 'api_auth_microsoft',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v1/auth/local/register',
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
                    content: new \ArrayObject([
                        'application/json' => new \ArrayObject([
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
            name: 'api_auth_local_register',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v1/auth/sms/register',
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
                    content: new \ArrayObject([
                        'application/json' => new \ArrayObject([
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
            name: 'api_auth_sms_register',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v1/auth/local/reset',
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
                    content: new \ArrayObject([
                        'application/json' => new \ArrayObject([
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
            name: 'api_auth_local_reset',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
        new Post(
            uriTemplate: '/v1/auth/sms/reset',
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
                    content: new \ArrayObject([
                        'application/json' => new \ArrayObject([
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
            name: 'api_auth_sms_reset',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
    ],
)]
#[UniqueEntity(fields: ['uuid'], message: 'There is already an account with this uuid')]
#[ORM\HasLifecycleCallbacks]
class User extends CustomSamlUserFactory implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $uuid = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $saml_identifier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $first_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $last_name = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserRadiusProfile::class)]
    private Collection $userRadiusProfiles;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserExternalAuth::class)]
    private Collection $userExternalAuths;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $verificationCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedAt = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Event::class, orphanRemoval: true)]
    private Collection $event;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notification;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(type: 'phone_number', nullable: true)]
    private ?PhoneNumber $phoneNumber = null;

    #[ORM\Column(nullable: true)]
    private ?bool $forgot_password_request = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?DeletedUserData $deletedUserData = null;

    #[ORM\Column]
    private ?bool $isDisabled = false;


    public function __construct()
    {
        $this->userRadiusProfiles = new ArrayCollection();
        $this->userExternalAuths = new ArrayCollection();
        $this->event = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->uuid;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->uuid;
    }

    public function setUsername(string $username): self
    {
        $this->uuid = $username;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getSamlIdentifier(): ?string
    {
        return $this->saml_identifier;
    }

    public function setSamlIdentifier(?string $saml_identifier): self
    {
        $this->saml_identifier = $saml_identifier;

        return $this;
    }

    public function setSamlAttributes(array $attributes): void
    {
        $this->uuid = $attributes['samlUuid'][0];
        $this->email = $attributes['email'][0] ?? '';
        $this->first_name = $attributes['givenName'][0];
        $this->last_name = $attributes['surname'][0] ?? ''; // set surname to empty string if null
        $this->password = 'notused'; //invalid hash so won't ever authenticate
        $this->isVerified = true;
        $this->isDisabled = false;
        // #$this->setLevel(LevelType::NONE);
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(?string $first_name): self
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(?string $last_name): self
    {
        $this->last_name = $last_name;

        return $this;
    }

    /**
     * @return Collection<int, UserRadiusProfile>
     */
    public function getUserRadiusProfiles(): Collection
    {
        return $this->userRadiusProfiles;
    }

    public function addUserRadiusProfile(UserRadiusProfile $userRadiusProfile): self
    {
        if (!$this->userRadiusProfiles->contains($userRadiusProfile)) {
            $this->userRadiusProfiles->add($userRadiusProfile);
            $userRadiusProfile->setUser($this);
        }

        return $this;
    }

    public function removeUserRadiusProfile(UserRadiusProfile $userRadiusProfile): self
    {
        if ($this->userRadiusProfiles->removeElement($userRadiusProfile)) {
            // set the owning side to null (unless already changed)
            if ($userRadiusProfile->getUser() === $this) {
                $userRadiusProfile->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserExternalAuth>
     */
    public function getUserExternalAuths(): Collection
    {
        return $this->userExternalAuths;
    }

    public function addUserExternalAuth(UserExternalAuth $userExternalAuth): self
    {
        if (!$this->userExternalAuths->contains($userExternalAuth)) {
            $this->userExternalAuths->add($userExternalAuth);
            $userExternalAuth->setUser($this);
        }

        return $this;
    }

    public function removeUserExternalAuth(UserExternalAuth $userExternalAuth): self
    {
        if ($this->userExternalAuths->removeElement($userExternalAuth)) {
            // set the owning side to null (unless already changed)
            if ($userExternalAuth->getUser() === $this) {
                $userExternalAuth->setUser(null);
            }
        }

        return $this;
    }

    public function getVerificationCode(): ?string
    {
        return $this->verificationCode;
    }

    public function setVerificationCode(?string $verificationCode): self
    {
        $this->verificationCode = $verificationCode;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getBannedAt(): ?\DateTimeInterface
    {
        return $this->bannedAt;
    }

    public function setBannedAt(?\DateTimeInterface $bannedAt): self
    {
        $this->bannedAt = $bannedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function prePresist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvent(): Collection
    {
        return $this->event;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->event->contains($event)) {
            $this->event->add($event);
            $event->setUser($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->event->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getUser() === $this) {
                $event->setUser(null);
            }
        }

        return $this;
    }

    public function getNotification(): Collection
    {
        return $this->notification;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->event->contains($notification)) {
            $this->event->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->event->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?PhoneNumber $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function isForgotPasswordRequest(): ?bool
    {
        return $this->forgot_password_request;
    }

    public function setForgotPasswordRequest(?bool $forgot_password_request): static
    {
        $this->forgot_password_request = $forgot_password_request;

        return $this;
    }

    public function getDeletedUserData(): ?DeletedUserData
    {
        return $this->deletedUserData;
    }

    public function setDeletedUserData(DeletedUserData $deletedUserData): static
    {
        // set the owning side of the relation if necessary
        if ($deletedUserData->getUser() !== $this) {
            $deletedUserData->setUser($this);
        }

        $this->deletedUserData = $deletedUserData;

        return $this;
    }

    public function toApiResponse(array $additionalData = []): array
    {
        $userExternalAuths = $this->getUserExternalAuths()->map(
            function (UserExternalAuth $userExternalAuth) {
                return [
                    'provider' => $userExternalAuth->getProvider(),
                    'provider_id' => $userExternalAuth->getProviderId(),
                ];
            }
        )->toArray();

        $responseData = [
            'uuid' => $this->getUuid(),
            'email' => $this->getEmail(),
            'roles' => $this->getRoles(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'user_external_auths' => $userExternalAuths,
        ];

        return array_merge($responseData, $additionalData);
    }

    public function isDisabled(): ?bool
    {
        return $this->isDisabled;
    }

    public function setDisabled(bool $isDisabled): static
    {
        $this->isDisabled = $isDisabled;

        return $this;
    }
}
