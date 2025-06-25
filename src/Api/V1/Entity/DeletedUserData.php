<?php

namespace App\Api\V1\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Api\V1\Controller\UserAccountController;
use App\Repository\DeletedUserDataRepository;
use ArrayObject;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeletedUserDataRepository::class)]
#[ApiResource(
    operations: [
        new Delete(
            uriTemplate: '/api/v1/userAccount/deletion',
            controller: UserAccountController::class,
            openapi: new Operation(
                responses: [
                    200 => [
                        'description' => 'User Account was deleted successfully.',
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
                                                    'example' => 'User with UUID "%s" successfully deleted.',
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
                                        'message' => 'User with UUID "test@openroaming.com" successfully deleted.',
                                        // phpcs:enable
                                    ],
                                ],
                            ],
                        ],
                    ],
                    400 => [
                        'description' => 'Bad Request or Missing Fields',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => ['type' => 'string'],
                                        'details' => [
                                            'type' => 'object',
                                            'nullable' => true,
                                            'properties' => [
                                                'missing_fields' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'string',
                                                    ],
                                                    'example' => ['password'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'examples' => [
                                    'missing_password_field' => [
                                        'summary' => 'Missing required password field',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid data: Missing required fields.',
                                            'details' => [
                                                'missing_fields' => ['password'],
                                            ],
                                        ],
                                    ],
                                    'invalid_json_format' => [
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
                    401 => [
                        'description' => 'Authentication failed or invalid credentials',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => ['type' => 'string'],
                                    ],
                                ],
                                'examples' => [
                                    'jwt_invalid' => [
                                        'summary' => 'Invalid JWT Token',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid Request: JWT Token is invalid!',
                                        ],
                                    ],
                                    'auth_failed_portal' => [
                                        'summary' => 'Invalid Portal Account Credentials',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid credentials: The provided password is incorrect.',
                                        ],
                                    ],
                                    'auth_failed_saml' => [
                                        'summary' => 'SAML Authentication Failed',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Authentication Failed: Unable to validate SAML assertion.',
                                        ],
                                    ],
                                    'auth_failed_google_microsoft' => [
                                        'summary' => 'Invalid Google/Microsoft Authentication Code',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Authentication Failed: Invalid or expired authorization code.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    403 => [
                        'description' => 'Access Denied or Unauthorized Actions',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
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
                                    'saml_email_mismatch' => [
                                        'summary' => 'SAML Verification Failed',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Unauthorized: The SAML assertion email does not match the user account email.',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'saml_configuration_error' => [
                                        'summary' => 'SAML Configuration Mismatch',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'The configured IDP Entity ID does not match the expected value. Access denied.',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'invalid_2fa_type' => [
                                        'summary' => 'Invalid Two-Factor Authentication Type',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Invalid Two-Factor Authentication configuration. Please ensure that 2FA is set up using either email or SMS for this account.',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'incomplete_2fa_setup' => [
                                        'summary' => 'Incomplete Two-Factor Authentication Setup',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'The Two-Factor Authentication (2FA) configuration is incomplete. Please set up 2FA using either email or SMS.',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'invalid_account_type' => [
                                        'summary' => 'Invalid Account Type',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'Invalid account type. Please only use email/phone number accounts from the portal.',
                                            // phpcs:enable
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    404 => [
                        'description' => 'Resource Not Found',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => ['type' => 'string'],
                                    ],
                                ],
                                'examples' => [
                                    'user_not_found' => [
                                        'summary' => 'User Not Found',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Invalid Account: User account not found.',
                                        ],
                                    ],
                                    'external_service_data_missing' => [
                                        'summary' => 'External Data Not Found',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'Required data from the external service could not be located.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    500 => [
                        'description' => 'Internal Server Error or Critical Process Failure',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'error' => ['type' => 'string'],
                                    ],
                                ],
                                'examples' => [
                                    'user_deletion_failure' => [
                                        'summary' => 'Error during user deletion',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'An error occurred while deleting the user.',
                                        ],
                                    ],
                                    'external_service_error' => [
                                        'summary' => 'Failure in External Service',
                                        'value' => [
                                            'success' => false,
                                            // phpcs:disable Generic.Files.LineLength.TooLong
                                            'error' => 'An error occurred while communicating with an external service.',
                                            // phpcs:enable
                                        ],
                                    ],
                                    'unexpected_error' => [
                                        'summary' => 'Uncaught Internal Server Error',
                                        'value' => [
                                            'success' => false,
                                            'error' => 'An unexpected error occurred. Please try again later.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Delete the authenticated user account',
                description: 'This endpoint deletes the currently authenticated user account.
                 Depending on the authentication method, the request body may require a password (Portal Account),
                  a SAMLResponse (SAML), or an authorization code (Google/Microsoft). 
                  The request verifies the provided authentication details before performing the account deletion.',
                parameters: [
                    new Parameter(
                        name: 'Authorization',
                        in: 'header',
                        description: 'Bearer token is required for authentication. 
                                        Use the format: `Bearer <JWT token>`.',
                        required: true,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
                requestBody: new RequestBody(
                    description: 'Conditional payload required for deleting a user, 
                    based on the external authentication provider.',
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'oneOf' => [
                                    [
                                        'type' => 'object',
                                        'required' => ['password'],
                                        'properties' => [
                                            'password' => [
                                                'type' => 'string',
                                                'description' => 'Password used for Portal accounts.',
                                                'example' => 'user-password-example',
                                            ],
                                        ],
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'description' => 'Payload structure for users authenticated via Portal Account.',
                                        // phpcs:enable
                                    ],
                                    [
                                        'type' => 'object',
                                        'required' => ['SAMLResponse'],
                                        'properties' => [
                                            'SAMLResponse' => [
                                                'type' => 'string',
                                                'description' => 'SAML response for users authenticated via SAML.',
                                                'example' => 'base64EncodedSAMLResponseHere',
                                            ],
                                        ],
                                        'description' => 'Payload structure for users authenticated via SAML.',
                                    ],
                                    [
                                        'type' => 'object',
                                        'required' => ['code'],
                                        'properties' => [
                                            'code' => [
                                                'type' => 'string',
                                                // phpcs:disable Generic.Files.LineLength.TooLong
                                                'description' => 'Authorization code used for Google/Microsoft accounts.',
                                                // phpcs:enable
                                                'example' => '4/AABEsG...',
                                            ],
                                        ],
                                        // phpcs:disable Generic.Files.LineLength.TooLong
                                        'description' => 'Authentication payload for users with Google or Microsoft accounts.',
                                        // phpcs:enable
                                    ],
                                ],
                                'description' => 'Payload structure depends on the external authentication provider.',
                            ],
                        ],
                    ]),
                ),
                security: [
                    [
                        'bearerAuth' => [],
                    ]
                ],
            ),
            shortName: 'User Account',
            name: 'api_user_account_deletion',
            extraProperties: [OpenApiFactory::OVERRIDE_OPENAPI_RESPONSES => false],
        ),
    ],
)]
class DeletedUserData
{
}
