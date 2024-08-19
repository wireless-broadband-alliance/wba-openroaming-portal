<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Api\V1\Controller\AuthsController;
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
            shortName: 'User',
            security: "is_granted('ROLE_USER')",
            securityMessage: "You don't have permission to access this resource",
            name: 'api_get_current_user',
            openapiContext: [
                'summary' => 'Retrieve current authenticated user',
                'description' => 'This endpoint returns the details of the currently authenticated user and 
                requires a valid CAPTCHA token.',
                'requestBody' => [
                    'description' => 'CAPTCHA validation token is required in the request body to retrieve 
                    the current authenticated user.',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                    ],
                                ],
                                'required' => ['cf-turnstile-response'],
                            ],
                            'example' => [
                                'cf-turnstile-response' => 'valid_test_token',
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'uuid' => ['type' => 'string'],
                                        'email' => ['type' => 'string'],
                                        'roles' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string']
                                        ],
                                        'isVerified' => ['type' => 'boolean'],
                                        'phone_number' => ['type' => 'string'],
                                        'firstName' => ['type' => 'string'],
                                        'lastName' => ['type' => 'string'],
                                        'verification_code' => ['type' => 'int'],
                                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                                        'bannedAt' => ['type' => 'string', 'format' => 'date-time'],
                                        'deletedAt' => ['type' => 'string', 'format' => 'date-time'],
                                        'forgot_password_request' => ['type' => 'boolean'],
                                    ],
                                ],
                                'example' => [
                                    'uuid' => 'abc123',
                                    'email' => 'user@example.com',
                                    'roles' => ["ROLE_USER"],
                                    'isVerified' => true,
                                    'phone_number' => '+19700XXXXXX',
                                    'firstName' => 'John',
                                    'lastName' => 'Doe',
                                    'verification_code' => 123456,
                                    'createdAt' => '2023-01-01T00:00:00+00:00',
                                    'bannedAt' => '2023-01-01T00:00:00+00:00',
                                    'deletedAt' => '2023-01-01T00:00:00+00:00',
                                    'forgot_password_request' => false
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request due to CAPTCHA validation failure',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'CAPTCHA validation failed.',
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Unauthorized',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'Unauthorized',
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
            controller: AuthsController::class,
            shortName: 'User Auth',
            name: 'api_auth_local',
            openapiContext: [
                'summary' => 'Authenticate a user locally',
                'description' => 'This endpoint authenticates a user using their UUID, password, 
                and a Turnstile CAPTCHA token.',
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
                                        'token' => ['type' => 'string', 'example' => 'jwt-token-example'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'uuid' => ['type' => 'string', 'example' => 'user-uuid-example'],
                                                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => ['type' => 'string', 'example' => 'John'],
                                                'last_name' => ['type' => 'string', 'example' => 'Doe'],
                                                'isVerified' => ['type' => 'boolean', 'example' => true],
                                                'createdAt' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'example' => '2023-01-01T00:00:00+00:00'
                                                ],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => [
                                                                'type' => 'string',
                                                                'example' => 'PortalAccount'
                                                            ],
                                                            'provider_id' => [
                                                                'type' => 'string',
                                                                'example' => 'provider-id-example'
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'token' => 'jwt-token-example',
                                    'user' => [
                                        'uuid' => 'user-uuid-example',
                                        'email' => 'user@example.com',
                                        'roles' => ['ROLE_USER'],
                                        'first_name' => 'John',
                                        'last_name' => 'Doe',
                                        'isVerified' => true,
                                        'createdAt' => '2023-01-01T00:00:00+00:00',
                                        'user_external_auths' => [
                                            [
                                                'provider' => 'PortalAccount',
                                                'provider_id' => 'provider-id-example',
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
                                'example' => [
                                    'error' => 'CAPTCHA validation failed or invalid data',
                                ],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'User not found or invalid credentials',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'Invalid data',
                                ],
                            ],
                        ],
                    ],
                    '403' => [
                        'description' => 'Invalid credentials - Provider not allowed',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'Invalid credentials - Provider not allowed',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/saml',
            controller: AuthsController::class,
            shortName: 'User Auth',
            name: 'api_auth_saml',
            openapiContext: [
                'summary' => 'Authenticate a user via SAML',
                'description' => 'This endpoint authenticates a user using their SAML account name 
                and a Turnstile CAPTCHA token.',
                'requestBody' => [
                    'description' => 'SAML account name and CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'sAMAccountName' => [
                                        'type' => 'string',
                                        'example' => 'saml-account-name-example'
                                    ],
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['sAMAccountName', 'cf-turnstile-response'],
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
                                        'token' => ['type' => 'string', 'example' => 'jwt-token-example'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer', 'example' => 1],
                                                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'uuid' => ['type' => 'string', 'example' => 'user-uuid-example'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => ['type' => 'string', 'example' => 'John'],
                                                'last_name' => ['type' => 'string', 'example' => 'Doe'],
                                                'isVerified' => ['type' => 'boolean', 'example' => true],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => [
                                                                'type' => 'string',
                                                                'example' => 'PortalAccount'
                                                            ],
                                                            'provider_id' => [
                                                                'type' => 'string',
                                                                'example' => 'provider-id-example'
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                'createdAt' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'example' => '2023-01-01 00:00:00'
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'token' => 'jwt-token-example',
                                    'user' => [
                                        'id' => 1,
                                        'email' => 'user@example.com',
                                        'uuid' => 'user-uuid-example',
                                        'roles' => ['ROLE_USER'],
                                        'first_name' => 'John',
                                        'last_name' => 'Doe',
                                        'isVerified' => true,
                                        'user_external_auths' => [
                                            [
                                                'provider' => 'PortalAccount',
                                                'provider_id' => 'provider-id-example',
                                            ],
                                        ],
                                        'createdAt' => '2023-01-01 00:00:00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request due to invalid data or CAPTCHA validation failure',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'CAPTCHA validation failed or invalid data',
                                ],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'User not found or provider not associated',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'User not found',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/google',
            controller: AuthsController::class,
            shortName: 'User Auth',
            name: 'api_auth_google',
            openapiContext: [
                'summary' => 'Authenticate a user via Google',
                'description' => 'This endpoint authenticates a user using their Google account ID. 
                It also requires CAPTCHA validation.',
                'requestBody' => [
                    'description' => 'Google account ID and CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'googleId' => ['type' => 'string', 'example' => 'google-account-id-example'],
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['googleId', 'cf-turnstile-response'],
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
                                        'token' => ['type' => 'string', 'example' => 'jwt-token-example'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer', 'example' => 1],
                                                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'uuid' => ['type' => 'string', 'example' => 'user-uuid-example'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                                'first_name' => ['type' => 'string', 'example' => 'John'],
                                                'last_name' => ['type' => 'string', 'example' => 'Doe'],
                                                'isVerified' => ['type' => 'boolean', 'example' => true],
                                                'user_external_auths' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'provider' => ['type' => 'string', 'example' => 'Google'],
                                                            'provider_id' => [
                                                                'type' => 'string',
                                                                'example' => 'google-id-example'
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                'createdAt' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                    'example' => '2023-01-01 00:00:00',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'token' => 'jwt-token-example',
                                    'user' => [
                                        'id' => 1,
                                        'email' => 'user@example.com',
                                        'uuid' => 'user-uuid-example',
                                        'roles' => ['ROLE_USER'],
                                        'first_name' => 'John',
                                        'last_name' => 'Doe',
                                        'isVerified' => true,
                                        'user_external_auths' => [
                                            [
                                                'provider' => 'Google',
                                                'provider_id' => 'google-id-example',
                                            ],
                                        ],
                                        'createdAt' => '2023-01-01 00:00:00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request due to invalid data or CAPTCHA validation failure',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'CAPTCHA validation failed or invalid data',
                                ],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'User not found or provider not associated',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'User not found',
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
            shortName: 'User Auth Register',
            name: 'api_auth_local_register',
            openapiContext: [
                'summary' => 'Register a new user via local authentication',
                'description' => 'This endpoint registers a new user using their email 
                and validates the request with a Turnstile CAPTCHA token.',
                'requestBody' => [
                    'description' => 'User registration data and CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uuid' => [
                                        'type' => 'string',
                                        'example' => 'user@example.com',
                                        'description' => 'User UUID, typically the same as the email'
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'example' => 'strongpassword',
                                        'description' => 'The user password, must be strong and secure'
                                    ],
                                    'email' => [
                                        'type' => 'string',
                                        'example' => 'user@example.com',
                                        'description' => 'User email address'
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
                                    'isVerified' => [
                                        'type' => 'boolean',
                                        'example' => false,
                                        'description' => 'Indicates if the user\'s email is verified'
                                    ],
                                    'createdAt' => [
                                        'type' => 'string',
                                        'format' => 'date-time',
                                        'example' => '2023-01-01 00:00:00',
                                        'description' => 'Account creation date and time'
                                    ],
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['uuid', 'password', 'email', 'cf-turnstile-response'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'User registered successfully',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'message' => 'Local User Account Registered Successfully',
                                ],
                            ],
                        ],
                    ],
                    '422' => [
                        'description' => 'Invalid data',
                        'content' => [
                            'application/json' => [
                                'examples' => [
                                    'missing_data' => [
                                        'summary' => 'Missing required data',
                                        'value' => ['error' => 'Invalid data: Missing required fields'],
                                    ],
                                    'mismatch_data' => [
                                        'summary' => 'UUID and email mismatch',
                                        'value' => [
                                            'error' => 'Invalid data: UUID and email do not match. 
                                            Ensure both fields contain the same content!',
                                        ],
                                    ],
                                    'captcha_failed' => [
                                        'summary' => 'CAPTCHA validation failed',
                                        'value' => [
                                            'error' => 'CAPTCHA validation failed',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '403' => [
                        'description' => 'User already exists',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'This User already exists',
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
            shortName: 'User Auth Register',
            name: 'api_auth_sms_register',
            openapiContext: [
                'summary' => 'Register a new user via SMS authentication',
                'description' => 'This endpoint registers a new user using their phone number 
                and validates the request with a Turnstile CAPTCHA token.',
                'requestBody' => [
                    'description' => 'User registration data and CAPTCHA validation token',
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'uuid' => [
                                        'type' => 'string',
                                        'example' => '+1234567890',
                                        'description' => 'User UUID, typically the same as the phone number'
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'example' => 'strongpassword',
                                        'description' => 'The user password, must be strong and secure'
                                    ],
                                    'phoneNumber' => [
                                        'type' => 'string',
                                        'example' => '+1234567890',
                                        'description' => 'User phone number'
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
                                    'isVerified' => [
                                        'type' => 'boolean',
                                        'example' => false,
                                        'description' => 'Indicates if the user\'s phone number is verified'
                                    ],
                                    'createdAt' => [
                                        'type' => 'string',
                                        'format' => 'date-time',
                                        'example' => '2023-01-01 00:00:00',
                                        'description' => 'Account creation date and time'
                                    ],
                                    'cf-turnstile-response' => [
                                        'type' => 'string',
                                        'description' => 'The CAPTCHA validation token',
                                        'example' => 'valid_test_token'
                                    ],
                                ],
                                'required' => ['uuid', 'password', 'phoneNumber', 'cf-turnstile-response'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'User registered successfully',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'message' => 'SMS User Account Registered Successfully',
                                ],
                            ],
                        ],
                    ],
                    '422' => [
                        'description' => 'Invalid data',
                        'content' => [
                            'application/json' => [
                                'examples' => [
                                    'missing_data' => [
                                        'summary' => 'Missing required data',
                                        'value' => ['error' => 'Invalid data: Missing required fields'],
                                    ],
                                    'mismatch_data' => [
                                        'summary' => 'UUID and phone number mismatch',
                                        'value' => [
                                            'error' => 'Invalid data: UUID and phone number do not match. 
                                            Ensure both fields contain the same content!',
                                        ],
                                    ],
                                    'captcha_failed' => [
                                        'summary' => 'CAPTCHA validation failed',
                                        'value' => [
                                            'error' => 'CAPTCHA validation failed',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '403' => [
                        'description' => 'User already exists',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'error' => 'This User already exists',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new Post(
            uriTemplate: '/v1/auth/local/reset',
            controller: AuthsController::class,
            shortName: 'User Auth Reset',
            name: 'api_auth_local_reset',
            openapiContext: [
                'summary' => 'Trigger a password reset for a local auth account',
                'description' => 'This endpoint triggers a password reset for a local auth account. 
                It verifies if the user has an external auth with "PortalAccount" and "EMAIL" providerId, 
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
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'We have sent you a new email to: user@example.com.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request - Invalid data or CAPTCHA validation failed',
                        'content' => [
                            'application/json' => [
                                'examples' => [
                                    'missing_data' => [
                                        'summary' => 'Missing required data',
                                        'value' => [
                                            'error' => 'Invalid Data. Please make sure to place the JWT Token',
                                        ],
                                    ],
                                    'captcha_invalid' => [
                                        'summary' => 'Invalid CAPTCHA token',
                                        'value' => [
                                            'error' => 'Invalid CAPTCHA token. Please try again.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '403' => [
                        'description' => 'Forbidden',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Invalid credentials - Provider not allowed',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '429' => [
                        'description' => 'Too Many Requests',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Please wait 2 minutes before trying again.',
                                        ],
                                    ],
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
        new Post(
            uriTemplate: '/v1/auth/sms/reset',
            controller: AuthsController::class,
            shortName: 'User Auth Reset',
            name: 'api_auth_sms_reset',
            openapiContext: [
                'summary' => 'Trigger a password reset for an SMS auth account',
                'description' => 'This endpoint sends an SMS with a new verification code if the user
                 has a valid PortalAccount and has not exceeded the SMS request limits. 
                 It also checks if the required time interval has passed before allowing a new request.',
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
                        'description' => 'Successfully sent the SMS with the new verification code',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => [
                                            'type' => 'string',
                                            'example' => 'We have sent a new code to: +1234567890. 
                                            You have 3 attempt(s) left.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Bad Request - Invalid data or CAPTCHA validation failed',
                        'content' => [
                            'application/json' => [
                                'examples' => [
                                    'missing_data' => [
                                        'summary' => 'Missing required data',
                                        'value' => [
                                            'error' => 'Invalid Credentials, Provider not allowed',
                                        ],
                                    ],
                                    'captcha_invalid' => [
                                        'summary' => 'Invalid CAPTCHA token',
                                        'value' => [
                                            'error' => 'Invalid CAPTCHA token. Please try again.',
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
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Please wait 2 minute(s) before trying again.',
                                        ],
                                    ],
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
        )
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
    /**
     * User Unique Identification Definition
     */
    #[ORM\Column(length: 180, unique: true)]
    private ?string $uuid = null;
    /**
     *  Associated Roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;
    /**
     * System verification status
     */
    #[ORM\Column(type: 'boolean')]
    private $isVerified = false;
    /**
     * User saml identifier (not mandatory, only if it's a SAML account)
     */
    #[ORM\Column(length: 255, nullable: true)]
    public ?string $saml_identifier = null;
    /**
     * User first name
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $first_name = null;
    /**
     * User last name
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $last_name = null;
    /**
     * User radius account identifier to generate passpoint provisioning profiles (foreign key)
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserRadiusProfile::class)]
    private Collection $userRadiusProfiles;
    /**
     * User radius account identifier for authentications request (foreign key)
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserExternalAuth::class)]
    private Collection $userExternalAuths;
    /**
     * User last verification code
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $verificationCode = null;
    /**
     * User google account identificationr (not mandatoru, only if it's a google account)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;
    /**
     * User creation date
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;
    /**
     * User ban date
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bannedAt = null;
    /**
     * User event identifcation logger (foreign key)
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Event::class, orphanRemoval: true)]
    private Collection $event;
    /**
     * User deletion date
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;
    /**
     * User phone number (not mandatory, only if it's a phone number account)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneNumber = null;
    /**
     * User forgot_passsowrd_request
     */
    #[ORM\Column(nullable: true)]
    private ?bool $forgot_password_request = null;
    /**
     * User deleted data identification (foreign key)
     */
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
}
