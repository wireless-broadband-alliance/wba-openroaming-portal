<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Api\V1\Controller\AuthController;
use App\Api\V1\Controller\GenerateJwtSamlController;
use App\Api\V1\Controller\GetCurrentUserController;
use App\Api\V1\Controller\RegistrationController;
use App\Repository\UserRepository;
use App\Security\CustomSamlUserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
            shortName: '🔒 User',
            security: "is_granted('ROLE_USER')",
            securityMessage: "You don't have permission to access this resource",
            paginationEnabled: false,
            name: 'api_get_current_user',
            openapiContext: [
                'summary' => 'Retrieve current authenticated user',
                'description' => 'This endpoint returns the details of the currently authenticated user.',
                'responses' => [
                    '200' => [
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
                                                    'items' => ['type' => 'string']
                                                ],
                                                'first_name' => ['type' => 'string'],
                                                'last_name' => ['type' => 'string'],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => ['type' => 'string'],
                                                            'provider_id' => ['type' => 'string']
                                                        ]
                                                    ]
                                                ],
                                                'phone_number' => ['type' => 'string', 'nullable' => true],
                                                'isVerified' => ['type' => 'boolean'],
                                                'user_radius_profiles' => ['type' => 'object'],
                                                'banned_at' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'nullable' => true
                                                ],
                                                'deleted_at' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'nullable' => true
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
                                        'first_name' => 'Nbo',
                                        'last_name' => 'Saltitante',
                                        'user_external_auths' => [
                                            [
                                                'provider' => 'Portal Account',
                                                'provider_id' => 'Email || Phone Number'
                                            ],
                                        ],
                                        'phone_number' => null,
                                        'isVerified' => true,
                                        'user_radius_profiles' => [],
                                        'banned_at' => null,
                                        'deleted_at' => null,
                                        'forgot_password_request' => false,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Unauthorized - Access token is missing, invalid, or user account is unverified/banned.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'error' => ['type' => 'string'],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'error' => 'Unauthorized - You do not have permission to access this resource.',
                                ],
                            ],
                        ],
                    ],
                ],
                'security' => [
                    [
                        'BearerAuth' => [
                            'scheme' => 'Bearer',
                            'bearerFormat' => 'JWT',
                            'example' => 'Bearer <JWT_TOKEN>',
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/local',
            controller: AuthController::class,
            shortName: '🔓 User Auth',
            name: 'api_auth_local',
            openapiContext: [
                'summary' => 'Authenticate a user locally',
                'description' => 'This endpoint authenticates a user using their UUID, password, and a CAPTCHA token.',
                'requestBody' => [
                    'description' => 'User credentials and CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uuid' => ['type' => 'string', 'example' => 'user-uuid-example'],
                                    'password' => ['type' => 'string', 'example' => 'user-password-example'],
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['uuid', 'password', 'cf-turnstile-response'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
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
                                                'uuid' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => ['type' => 'string', 'example' => 'John'],
                                                'last_name' => ['type' => 'string', 'example' => 'Doe'],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => [
                                                                'type' => 'string',
                                                                'example' => 'Portal Account'
                                                            ],
                                                            'provider_id' => ['type' => 'string', 'example' => 'Email'],
                                                        ],
                                                    ],
                                                ],
                                                'token' => [
                                                    'type' => 'string',
                                                    'example' => 'jwt_token'
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request due to invalid data or CAPTCHA validation failure',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'error' => [
                                                    'type' => 'string',
                                                    'example' => 'CAPTCHA validation failed or invalid data'
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'data' => [
                                        'error' => 'CAPTCHA validation failed or invalid data',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'User not found or invalid credentials',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'error' => ['type' => 'string', 'example' => 'Invalid credentials'],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'data' => [
                                        'error' => 'Invalid credentials',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'security' => [
                    [
                        'bearerAuth' => [],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/saml',
            controller: AuthController::class,
            shortName: '🔓 User Auth',
            name: 'api_auth_saml',
            openapiContext: [
                'summary' => 'Authenticate a user via SAML',
                'description' => 'This endpoint authenticates a user using their SAML response. 
                If the user is not found in the database, a new user will be created based on the SAML assertion. 
                The response includes user details along with a JWT token if authentication is successful.',
                'requestBody' => [
                    'description' => 'SAML response required for user authentication.
                     The request should be sent as `multipart/form-data` with the SAML response included as a 
                     form field (not a file).',
                    'required' => true,
                    'content' => [
                        'multipart/form-data' => [
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
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
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
                                                'user' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'id' => [
                                                            'type' => 'integer',
                                                            'description' => 'User ID',
                                                            'example' => 1,
                                                        ],
                                                        'email' => [
                                                            'type' => 'string',
                                                            'description' => 'User email address',
                                                            'example' => 'user@example.com',
                                                        ],
                                                        'uuid' => [
                                                            'type' => 'string',
                                                            'description' => 'User UUID',
                                                            'example' => 'user-uuid-example',
                                                        ],
                                                        'roles' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                'type' => 'string',
                                                            ],
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
                                                    ],
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
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        'user' => [
                                            'id' => 1,
                                            'email' => 'user@example.com',
                                            'uuid' => 'user-uuid-example',
                                            'roles' => ['ROLE_USER'],
                                            'first_name' => 'John',
                                            'last_name' => 'Doe',
                                        ],
                                        'token' => 'jwt-token-example',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request due to missing SAML response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'error' => [
                                                    'type' => 'string',
                                                    'description' => 'Error message explaining why the request failed',
                                                    'example' => 'SAML Response not found',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'data' => [
                                        'error' => 'SAML Response not found',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Unauthorized due to invalid SAML assertion',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'error' => [
                                                    'type' => 'string',
                                                    'description' => 'Error message for why authentication failed',
                                                    'example' => 'Invalid SAML Assertion',
                                                ],
                                                'details' => [
                                                    'type' => 'string',
                                                    'description' => 'Detailed error message',
                                                    'example' => 'Detailed error information from SAML assertion',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'data' => [
                                        'error' => 'Invalid SAML Assertion',
                                        'details' => 'Detailed error information from SAML assertion',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '500' => [
                        'description' => 'Server error while processing the SAML response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'error' => [
                                                    'type' => 'string',
                                                    'description' => 'Error message for why the server error occurred',
                                                    'example' => 'SAML processing error',
                                                ],
                                                'details' => [
                                                    'type' => 'string',
                                                    'description' => 'Detailed error message',
                                                    'example' => 'Detailed error information',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'data' => [
                                        'error' => 'SAML processing error',
                                        'details' => 'Detailed error information',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/google',
            controller: AuthController::class,
            shortName: '🔓 User Auth',
            name: 'api_auth_google',
            openapiContext: [
                'summary' => 'Authenticate a user via Google',
                'description' => 'This endpoint authenticates a user using their Google account ID.',
                'requestBody' => [
                    'description' => 'Google account ID and CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'googleId' => ['type' => 'string', 'example' => 'google-account-id-example'],
                                ],
                                'required' => ['googleId'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
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
                                                'token' => ['type' => 'string', 'example' => 'jwt-token-example'],
                                                'user' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'id' => ['type' => 'integer', 'example' => 1],
                                                        'email' => [
                                                            'type' => 'string',
                                                            'example' => 'user@example.com'
                                                        ],
                                                        'uuid' => [
                                                            'type' => 'string',
                                                            'example' => 'user-uuid-example'
                                                        ],
                                                        'roles' => [
                                                            'type' => 'array',
                                                            'items' => ['type' => 'string'],
                                                            'example' => ['ROLE_USER'],
                                                        ],
                                                        'first_name' => ['type' => 'string', 'example' => 'John'],
                                                        'last_name' => ['type' => 'string', 'example' => 'Doe'],
                                                        'user_external_auths' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                'type' => 'object',
                                                                'properties' => [
                                                                    'provider' => [
                                                                        'type' => 'string',
                                                                        'example' => 'Google'
                                                                    ],
                                                                    'provider_id' => [
                                                                        'type' => 'string',
                                                                        'example' => 'google-id-example'
                                                                    ],
                                                                ],
                                                            ],
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
                                        'token' => 'jwt-token-example',
                                        'user' => [
                                            'id' => 1,
                                            'email' => 'user@example.com',
                                            'uuid' => 'user-uuid-example',
                                            'roles' => ['ROLE_USER'],
                                            'first_name' => 'John',
                                            'last_name' => 'Doe',
                                            'user_external_auths' => [
                                                [
                                                    'provider' => 'Google',
                                                    'provider_id' => 'google-id-example',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Authentication failed due to invalid external auth or provider issues',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'error' => ['type' => 'string', 'example' => 'Authentication Failed'],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'data' => [
                                        'error' => 'Authentication Failed',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/local/register',
            controller: RegistrationController::class,
            shortName: '🔓 User Auth Register',
            name: 'api_auth_local_register',
            openapiContext: [
                'summary' => 'Register a new user via local authentication',
                'description' => 'This endpoint registers a new user using their email and validates 
                the request with a CAPTCHA token.',
                'requestBody' => [
                    'description' => 'User registration data and CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'example' => 'user@example.com',
                                        'description' => 'User email address'
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'example' => 'strongpassword',
                                        'description' => 'User password'
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
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['email', 'password', 'cf-turnstile-response'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
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
                                                    'example' => 'Local User Account Registered Successfully',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        'message' => 'Local User Account Registered Successfully',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
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
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'error' => [
                                                    'type' => 'string',
                                                    'description' => 'Error message for why the request failed',
                                                    'example' => 'Missing required fields or invalid data',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'missing_data' => [
                                        'summary' => 'Missing required data',
                                        'value' => [
                                            'success' => false,
                                            'data' => [
                                                // phpcs:disable Generic.Files.LineLength.TooLong
                                                'error' => 'Missing required fields: email, password or cf-turnstile-response',
                                                // phpcs:enable
                                            ],
                                        ],
                                    ],
                                    'invalid_data' => [
                                        'summary' => 'Invalid data format',
                                        'value' => [
                                            'success' => false,
                                            'data' => [
                                                'error' => 'Invalid data format for fields',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '409' => [
                        'description' => 'Conflict due to user already existing',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'error' => [
                                                    'type' => 'string',
                                                    // phpcs:disable Generic.Files.LineLength.TooLong
                                                    'description' => 'Error message when the user could not be registered',
                                                    // phpcs:enable
                                                    'example' => 'This User already exists',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'data' => [
                                        'error' => 'This User already exists',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/sms/register',
            controller: RegistrationController::class,
            shortName: '🔓 User Auth Register',
            name: 'api_auth_sms_register',
            openapiContext: [
                'summary' => 'Register a new user via SMS authentication',
                'description' => 'This endpoint registers a new user using their phone number and validates 
                the request with a CAPTCHA token.',
                'requestBody' => [
                    'description' => 'User registration data and CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'phoneNumber' => [
                                        'type' => 'string',
                                        'example' => '+1234567890',
                                        'description' => 'User phone number'
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'example' => 'strongpassword',
                                        'description' => 'User password'
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
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['phoneNumber', 'password', 'cf-turnstile-response'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
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
                                                    'example' => 'SMS User Account Registered Successfully',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        'message' => 'SMS User Account Registered Successfully',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
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
                                        'details' => [
                                            'type' => 'string',
                                            'description' => 'Detailed error message',
                                            'example' => 'Error information about missing fields or data validation',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'missing_data' => [
                                        'summary' => 'Missing required data',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Missing data',
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'details' => 'Missing required fields: phoneNumber, password or cf-turnstile-response',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'mismatch_data' => [
                                        'summary' => 'Phone number and data mismatch',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid data',
                                            'details' => 'Phone number or other data is invalid or does not match',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '409' => [
                        'description' => 'Conflict due to user already existing',
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
                                            'description' => 'Error message for why the user could not be registered',
                                            'example' => 'This User already exists',
                                        ],
                                        'details' => [
                                            'type' => 'string',
                                            'description' => 'Detailed error message',
                                            'example' => 'User with the provided phone number already exists',
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'error' => 'This User already exists',
                                    'details' => 'User with the provided phone number already exists',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/local/reset',
            controller: AuthController::class,
            shortName: '🔓 User Auth Reset',
            name: 'api_auth_local_reset',
            openapiContext: [
                'summary' => 'Trigger a password reset for a local auth account (Requires Authorization)',
                'description' => 'This endpoint triggers a password reset for a local auth account. 
                The user must be authenticated using a Bearer token. 
                To use this endpoint, click on the "Authorize" button at the top of the Swagger UI and
                 provide your JWT token in the format: `Bearer JWT_Token`. 
                 The endpoint verifies if the user has an external auth with "PortalAccount" and "EMAIL" providerId,
                 then proceeds with the password reset if the conditions are met.',
                'requestBody' => [
                    'description' => 'Password reset request data including CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['cf-turnstile-response'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
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
                                                    'example' => 'We have sent you a new email to: user@example.com.',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => true,
                                    'data' => [
                                        'message' => 'We have sent you a new email to: user@example.com.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request - Invalid data or CAPTCHA validation failed',
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
                                            'example' => 'Invalid Data',
                                        ],
                                        'details' => [
                                            'type' => 'string',
                                            'description' => 'Detailed error message',
                                            'example' => 'Invalid data or CAPTCHA validation failed.',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'missing_data' => [
                                        'summary' => 'Missing required data',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid Data',
                                            'details' => 'Please make sure to include the CAPTCHA token.',
                                        ],
                                    ],
                                    'captcha_invalid' => [
                                        'summary' => 'Invalid CAPTCHA token',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid CAPTCHA token',
                                            'details' => 'The CAPTCHA token provided is invalid. Please try again.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '403' => [
                        'description' => 'Forbidden - Invalid credentials or provider not allowed',
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
                                            'description' => 'Error message explaining why the request was forbidden',
                                            'example' => 'Invalid credentials - Provider not allowed',
                                        ],
                                        'details' => [
                                            'type' => 'string',
                                            'description' => 'Detailed error message',
                                            'example' => 'Invalid credentials or provider is not allowed to reset.',
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'error' => 'Invalid credentials - Provider not allowed',
                                    'details' => 'Forbidden due to invalid credentials or the provider is not allowed.',
                                ],
                            ],
                        ],
                    ],
                    '429' => [
                        'description' => 'Too Many Requests - Rate limit exceeded',
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
                                            'description' => 'Error message explaining why the rate limit was exceeded',
                                            'example' => 'Too Many Requests',
                                        ],
                                        'details' => [
                                            'type' => 'string',
                                            'description' => 'Detailed error message',
                                            'example' => 'Please wait 2 minutes before trying again.',
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'error' => 'Too Many Requests',
                                    'details' => 'Please wait 2 minutes before trying again.',
                                ],
                            ],
                        ],
                    ],
                ],
                'security' => [
                    [
                        'BearerAuth' => [] // This will require the user to authorize using JWT
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/sms/reset',
            controller: AuthController::class,
            shortName: '🔓 User Auth Reset',
            name: 'api_auth_sms_reset',
            openapiContext: [
                'summary' => 'Trigger a password reset for an SMS auth account',
                'description' => 'This endpoint sends an SMS with a new verification code if the user has a valid
                 PortalAccount and has not exceeded the SMS request limits. It also checks if the required time
                  interval has passed before allowing a new request.',
                'requestBody' => [
                    'description' => 'Password reset request data including CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token',
                                    ],
                                ],
                                'required' => ['cf-turnstile-response'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Successfully sent the SMS with the new verification code',
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
                                                    'example' => 'We have sent a new code to: +1234567890. You have 3 attempt(s) left.',
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
                                        'message' => 'We have sent a new code to: +1234567890. You have 3 attempt(s) left.',
                                        // phpcs:enable
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request - Invalid data or CAPTCHA validation failed',
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
                                            'example' => 'Invalid data or CAPTCHA validation failed',
                                        ],
                                        'details' => [
                                            'type' => 'string',
                                            'example' => 'Invalid data or CAPTCHA token.',
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'missing_data' => [
                                        'summary' => 'Missing required data',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid data',
                                            'details' => 'Please include the CAPTCHA token.',
                                        ],
                                    ],
                                    'captcha_invalid' => [
                                        'summary' => 'Invalid CAPTCHA token',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid CAPTCHA token',
                                            'details' => 'The CAPTCHA token provided is invalid. Please try again.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '429' => [
                        'description' => 'Too Many Requests - Rate limit exceeded or attempt limit reached',
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
                                            'example' => 'Rate limit exceeded',
                                        ],
                                        'details' => [
                                            'type' => 'string',
                                            'example' => 'Please wait 2 minute(s) before trying again.',
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'error' => 'Rate limit exceeded',
                                    'details' => 'Please wait 2 minute(s) before trying again.',
                                ],
                            ],
                        ],
                    ],
                    '500' => [
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
                                            'description' => 'Error message explaining why the server error occurred',
                                            'example' => 'An unexpected error occurred while processing the request',
                                        ],
                                        'details' => [
                                            'type' => 'string',
                                            'description' => 'Detailed error message',
                                            'example' => 'An unexpected error occurred while processing the request.',
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'success' => false,
                                    'error' => 'An unexpected error occurred while processing the request',
                                    'details' => 'An unexpected error occurred while processing the request.',
                                ],
                            ],
                        ],
                    ],
                ],
                'security' => [
                    [
                        'BearerAuth' => [
                            'type' => 'http',
                            'scheme' => 'bearer',
                            'bearerFormat' => 'JWT',
                        ],
                    ],
                ],
            ],
        ),
    ],
    openapiContext: [
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description' => 'JWT Authorization header using the Bearer scheme.',
                ],
            ],
        ],
        'security' => [
            [
                'bearerAuth' => [],
            ],
        ],
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
    private $isVerified = false;

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

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(nullable: true)]
    private ?bool $forgot_password_request = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?DeletedUserData $deletedUserData = null;


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
        $this->isVerified = 1;
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

    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;

        return $this;
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

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
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
}
