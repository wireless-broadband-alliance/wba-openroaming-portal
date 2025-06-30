<?php

namespace App\Service;

use App\Enum\ApiVersion;
use Symfony\Component\Routing\RouterInterface;

readonly class ApiResponseService
{
    public function __construct(
        private RouterInterface $router
    ) {
    }

    public function getRoutesByPrefix(string $version): array
    {
        $routes = $this->router->getRouteCollection();
        $grouped = [];
        $responses = $this->getResponseMetadata($version);

        $prefix = $version === ApiVersion::API_V1->value ? '/api/v1' : '/api/v2';

        foreach ($routes as $name => $route) {
            $path = $route->getPath();

            if ($path !== $prefix && str_starts_with($path, $prefix)) {
                // Extract the first segment after the version
                $relativePath = trim(str_replace($prefix, '', $path), '/');
                $segments = explode('/', $relativePath);
                $groupKey = $segments[0] ?? 'general';

                $grouped[$groupKey][] = [
                    'name' => $name,
                    'path' => $path,
                    'methods' => $route->getMethods(),
                    'responses' => $responses[$name]['responses'] ?? [],
                    'isProtected' => $responses[$name]['isProtected'] ?? false,
                    'description' => $responses[$name]['description'] ?? null,
                    'requestBody' => $responses[$name]['requestBody'],
                ];
            }
        }

        ksort($grouped);

        return $grouped;
    }

    private function getResponseMetadata(string $version): array
    {
        $apiResponseV1 = [
            'api_v1_auth_local' => [
                'requestBody' => [
                    'uuid' => 'user-uuid-example',
                    'password' => 'user-password-example',
                    'turnstile_token' => 'valid_test_token',
                    'twoFACode' => '02YZR88R'
                ],
                'description' => 'This endpoint authenticates a user using their UUID, password, and a CAPTCHA token. 
                Platform can require the authentication with Two-Factor, the twoFACode parameter will be asked based on 
                the TWO_FACTOR_AUTH_STATUS setting.',
                'responses' => [
                    200 => [
                        json_decode(
                            '{
                            "success": true,
                            "data": {
                                "uuid": "apitest2@api.com",
                                "email": "apitest2@api.com",
                                "roles": ["ROLE_USER"],
                                "first_name": null,
                                "last_name": null,
                                "user_external_auths": [
                                    {
                                        "provider": "Portal Account",
                                        "provider_id": "Email"
                                    }
                                ],
                                "token": "validToken"
                            }
                        }',
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        )
                    ],
                    400 => [
                        'CAPTCHA validation failed',
                        'Missing required fields: uuid, password or turnstile_token',
                        'Missing required configuration setting: TWO_FACTOR_AUTH_STATUS',
                        'Invalid json format',
                        'Invalid user provided. Please verify the user data'
                    ],
                    401 => [
                        'Two-Factor Authentication is active for this account. Please ensure you provide the correct 
                        authentication code.',
                        'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                        'Two-Factor Authentication it\'s required for authentication on the portal. 
                        Please visit DOMAIN to set up 2FA and secure your account.',
                        'Invalid credentials'
                    ],
                    403 => [
                        'User account is not verified!',
                        'User account is banned from the system!',
                        'Your request cannot be processed at this time due to a pending action. 
                        If your account is active, re-login to complete the action',
                    ],
                    500 => [
                        'An error occurred: Generic server-side error.',
                        'JWT key files are missing. Please ensure both private and public keys exist.',
                    ]
                ],

            ],
            'api_v1_auth_saml' => [
                'requestBody' => [],
                'description' => 'This endpoint authenticates a user using their SAML response. 
                If the user is not found in the database, a new user will be created based on the SAML assertion. 
                The response includes user details along with a JWT token if authentication is successful. 
                Also if the platform requires authentication with Two-Factor, the twoFACode parameter will 
                be asked based on the TWO_FACTOR_AUTH_STATUS setting.',
                'responses' => [
                    200 => [
                        'Registration successful. Please check your email for further instructions',
                    ],
                    400 => [
                        'SAML Response not found',
                        'Invalid user provided. Please verify the user data',
                    ],
                    401 => [
                        'Unable to validate SAML assertion',
                        'Authentication Failed',
                        'Two-Factor Authentication is active for this account. Please ensure you provide the correct 
                        authentication code.',
                        'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                        'Two-Factor Authentication it\'s required for authentication on the portal. 
                        Please visit DOMAIN to set up 2FA and secure your account.'
                    ],
                    403 => [
                        'The provided IDP Entity is invalid or does not match the expected configuration.',
                        'The provided certificate is invalid or does not match the expected configuration.',
                        'User account is not verified!',
                        'User account is banned from the system!',
                    ],
                    500 => [
                        'An error occurred: Generic server-side error.',
                        'JWT key files are missing. Please ensure both private and public keys exist.',
                    ]
                ],
            ],
            'api_v1_auth_google' => [
                'requestBody' => [
                    'code' => '4/0AdKgLCxjQ74mKAg9vs_f7PuO99DR',
                    'twoFACode' => '02YZR88R'
                ],
                'description' => 'This endpoint authenticates a user using their Google account. 
                A valid Google OAuth authorization code is required. If the user is successfully authenticated, 
                user details and a JWT token will be returned. Also if the platform requires authentication with 
                Two-Factor, the twoFACode parameter will be asked based on the TWO_FACTOR_AUTH_STATUS setting.',
                'responses' => [
                    200 => [
                        'Authenticated user details and JWT token'
                    ],
                    400 => [
                        'Invalid JSON format',
                        'Missing authorization code!',
                        'This code is not associated with a google account!',
                        'Invalid user provided. Please verify the user data',
                    ],
                    401 => [
                        'Two-Factor Authentication is active for this account. Please ensure you provide the correct 
                        authentication code.',
                        'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                        'Two-Factor Authentication it\'s required for authentication on the portal. 
                        Please visit DOMAIN to set up 2FA and secure your account.',
                    ],
                    403 => [
                        'User account is not verified!',
                        'User account is banned from the system!',
                        'our email domain is not allowed to use this platform!',
                    ],
                    500 => [
                        'An error occurred: Generic server-side error.',
                        'JWT key files are missing. Please ensure both private and public keys exist.',
                    ]
                ],
            ],
            'api_v1_auth_microsoft' => [
                'requestBody' => [
                    'code' => '0.AQk6Lf2I2XGhQkWlU8gBp0KmxeNn2KTcbsJh.8Qt3OeYCB4sQ2FHo',
                    'twoFACode' => '02YZR88R'
                ],
                'description' => 'This endpoint authenticates a user using their Microsoft account. 
                A valid Microsoft OAuth authorization code is required. If the user is successfully authenticated, 
                user details and a JWT token will be returned. Also if the platform requires authentication 
                with Two-Factor, the twoFACode parameter will be asked based on the TWO_FACTOR_AUTH_STATUS setting.',
                'responses' => [
                    200 => [
                        'Authenticated user details and JWT token',
                    ],
                    400 => [
                        'Invalid JSON format',
                        'Missing authorization code!',
                        'This code is not associated with a microsoft account!',
                        'Invalid user provided. Please verify the user data',
                    ],
                    401 => [
                        'Two-Factor Authentication is active for this account. Please ensure you provide the correct 
                        authentication code.',
                        'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                        'Two-Factor Authentication it\'s required for authentication on the portal. 
                        Please visit DOMAIN to set up 2FA and secure your account.',
                    ],
                    403 => [
                        'User account is not verified!',
                        'User account is banned from the system!',
                        'our email domain is not allowed to use this platform!',
                    ],
                    500 => [
                        'An error occurred: Generic server-side error.',
                        'JWT key files are missing. Please ensure both private and public keys exist.',
                    ]
                ]

            ],
            'api_v1_capport_json' => [
                'requestBody' => [],
                'description' => 'Returns JSON metadata for the Captive Portal (CAPPORT) configuration.',
                'responses' => [
                    200 => [
                        'Successful response with CAPPORT metadata.',
                    ],
                    404 => [
                        'CAPPORT is not enabled'
                    ]
                ]
            ],
            'api_v1_config_settings' => [
                'description' => 'This endpoint returns public values from the Setting entity and environment 
                variables categorized by platform and provider.',
                'requestBody' => [],
                'responses' => [
                    200 => [
                        'Configuration settings retrieved successfully'
                    ]
                ]
            ],
            'api_v1_get_current_user' => [
                'requestBody' => [],
                'description' => 'This endpoint returns the details of the currently authenticated user.',
                'isProtected' => true,
                'responses' => [
                    200 => [
                        'User details retrieved successfully'
                    ],
                    401 => [
                        'JWT Token not found!',
                        'JWT Token is invalid!',
                        'JWT Token is expired!',
                    ],
                    403 => [
                        'Unauthorized - You do not have permission to access this resource.',
                        'User account is not verified!',
                        'User account is banned from the system!',
                    ],
                ]
            ],
            'api_v1_config_profile_android' => [
                'requestBody' => [
                    'public_key' => '-----BEGIN PUBLIC KEY-----\\n<RSA_PUBLIC_KEY>\\n-----END PUBLIC KEY-----'
                ],
                'description' => 'This endpoint retrieves the profile configuration for Android, 
                including a user\'s radius profile data, encrypted password, and other relevant settings for the
                 Android application.',
                'isProtected' => true,
                'responses' => [
                    200 => [
                        'Profile configuration for Android successfully retrieved'
                    ],
                    400 => [
                        'Invalid or missing public key'
                    ],
                    401 => [
                        'JWT Token is invalid!'
                    ],
                    403 => [
                        'Unauthorized access!'
                    ],
                    500 => [
                        'Failed to encrypt the password',
                    ]
                ]
            ],
            'api_v1_config_profile_ios' => [
                'requestBody' => [
                    'public_key' => '-----BEGIN PUBLIC KEY-----\\n<RSA_PUBLIC_KEY>\\n-----END PUBLIC KEY-----'
                ],
                'description' => 'This endpoint retrieves the profile configuration for iOS, including a user\'s radius
                 profile data, encrypted password, and other relevant settings for the iOS application.',
                'isProtected' => true,
                'responses' => [
                    200 => [
                        'Profile configuration for iOS successfully retrieved',
                    ],
                    400 => [
                        'Invalid or missing public key',
                    ],
                    401 => [
                        'JWT Token is invalid!'
                    ],
                    403 => [
                        'Unauthorized access!'
                    ],
                    500 => [
                        'Failed to encrypt the password',
                    ]
                ]
            ],
            'api_v1_auth_local_register' => [
                'requestBody' => [
                    'email' => 'user@example.com',
                    'password' => 'strongpassword',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint registers a new user using their email and password, 
                with CAPTCHA validation via the Turnstile token. It handles user creation, password hashing,
                 and CAPTCHA verification. If the user already exists, it returns a conflict error.',
                'responses' => [
                    200 => [
                        'Registration successful. Please check your email for further instructions',
                    ],
                    400 => [
                        'Invalid email format.',
                        'Invalid JSON format',
                        'Missing required fields: email, password or turnstile_token',
                        'CAPTCHA validation failed'
                    ],
                ]
            ],
            'api_v1_auth_local_reset' => [
                'requestBody' => [
                    'email' => 'user@example.com',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint triggers a password reset for a local auth account. 
                The user must provide their email and a CAPTCHA validation token. The endpoint verifies if the user 
                has an external auth with "PortalAccount" and "EMAIL" providerId, then proceeds with the password 
                reset if the conditions are met.',
                'responses' => [
                    200 => [
                        'Password reset email sent successfully',
                    ],
                    400 => [
                        'Invalid email format.',
                        'Invalid JSON format',
                        'Missing required fields: email or turnstile_token',
                        'CAPTCHA validation failed'
                    ],
                ]
            ],
            'api_v1_auth_sms_register' => [
                'requestBody' => [
                    'country_code' => 'PT',
                    'phone_number' => '1234567890',
                    'password' => 'strongpassword',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint registers a new user using their phone number and validates the 
                request with a CAPTCHA token.',
                'responses' => [
                    200 => [
                        'SMS User Account Registered Successfully. A verification code has been sent to your phone.',
                    ],
                    400 => [
                        'CAPTCHA validation failed',
                        'Missing required fields: country code, phone number, password, or turnstile_token',
                        'Invalid phone number format or country code.',
                        'Invalid json format',
                    ],
                    500 => [
                        'Failed to send SMS',
                        'User registered but SMS could not be sent.',
                    ]
                ]
            ],
            'api_v1_auth_sms_reset' => [
                'requestBody' => [
                    'country_code' => 'PT',
                    'phone_number' => '1234567890',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint sends an SMS with a new password and verification code if the user 
                has a valid PortalAccount and has not exceeded SMS request limits. The endpoint also enforces the 
                time interval between requests and limits the number of attempts allowed.',
                'responses' => [
                    200 => [
                        'Successfully sent the SMS with a new password and verification code',
                    ],
                    400 => [
                        'CAPTCHA validation failed',
                        'Missing required fields: country code, phone number, turnstile_token',
                        'Invalid phone number format or country code.',
                        'Invalid JSON format',
                    ],
                    500 => [
                        'An unexpected error occurred while processing the request',
                    ]
                ]

            ],
            'api_v1_turnstile_html_android' => [
                'requestBody' => [
                    'success' => true,
                    'data' => '<html><body><h1>Turnstile Configuration</h1><p>This is the required HTML 
configuration for the Android App.</p></body></html>'
                ],
                'description' => 'This endpoint serves the public HTML configuration required for the Android App 
                to integrate with the Turnstile feature.',
                'responses' => [
                    200 => [
                        'Turnstile HTML configuration retrieved successfully',
                    ],
                    404 => [
                        'HTML file not found.',
                    ]
                ]
            ],
            'api_v1_twoFA_request' => [
                'requestBody' => [
                    'uuid' => 'user-uuid-example',
                    'password' => 'user-password-example',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint provides Two-Factor Authentication code only for portal accounts. 
                To be able to request a authentication code the account needs to have setup a 2fa with email or SMS.',
                'responses' => [
                    200 => [
                        'Requested two-factor authentication token',
                    ],
                    400 => [
                        'CAPTCHA validation failed',
                        'Missing required fields: uuid, password or turnstile_token',
                        'Missing required configuration setting: TWO_FACTOR_AUTH_RESEND_INTERVAL 
                        TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS',
                        'Invalid json format',
                        'Invalid credentials'
                    ],
                    401 => [
                        'Invalid credentials',
                        'Invalid credentials'
                    ],
                    403 => [
                        'User account is not verified!',
                        'User account is banned from the system!',
                        'Invalid account type. Please only use email/phone number accounts from the portal',
                        'Invalid Two-Factor Authentication configuration Please ensure that 2FA is set up using either 
                        email or SMS for this account',
                        'The Two-Factor Authentication (2FA) configuration is incomplete. Please set up 2FA using 
                        either email or SMS',
                        'Your request cannot be processed at this time due to a pending action. If your account is
                         active, re-login to complete the action',
                    ],
                    429 => [
                        'You need to wait %d seconds before asking for a new code.',
                        'Too many attempts. You have exceeded the limit of %d attempts. 
                        Please wait %d minutes before trying again.',
                        'Too many validation attempts. You have exceeded the limit of %d attempts. 
                        Please wait %d minute(s) before trying again.',
                    ]
                ]

            ],
            'api_v1_user_account_deletion' => [
                'requestBody' => [
                    'password' => 'user-password-example'
                ],
                'description' => 'This endpoint deletes the currently authenticated user account. 
                Depending on the authentication method, the request body may require a password (Portal Account), 
                a SAMLResponse (SAML), or an authorization code (Google/Microsoft). 
                The request verifies the provided authentication details before performing the account deletion.',
                'isProtected' => true,
                'responses' => [
                    200 => [
                        'User Account was deleted successfully.',
                    ],
                    400 => [
                        'Invalid data: Missing required fields.',
                        'Invalid JSON format',
                    ],
                    401 => [
                        'Invalid Request: JWT Token is invalid!',
                        'Invalid credentials: The provided password is incorrect.',
                        'Authentication Failed: Unable to validate SAML assertion.',
                        'Authentication Failed: Invalid or expired authorization code.',
                    ],
                    403 => [
                        'Unauthorized - You do not have permission to access this resource.',
                        'Unauthorized: The SAML assertion email does not match the user account email.',
                        'The configured IDP Entity ID does not match the expected value. Access denied.',
                        'Invalid Two-Factor Authentication configuration. Please ensure that 2FA is set up using 
                        either email or SMS for this account.',
                        'The Two-Factor Authentication (2FA) configuration is incomplete. Please set up 2FA using 
                        either email or SMS.',
                        'Invalid account type. Please only use email/phone number accounts from the portal.',
                    ],
                    404 => [
                        'Invalid Account: User account not found.',
                        'Required data from the external service could not be located.',
                    ],
                    500 => [
                        'An error occurred while deleting the user.',
                        'An error occurred while communicating with an external service.',
                        'An unexpected error occurred. Please try again later.',
                    ]
                ]
            ]
        ];
        $apiResponseV2 = [
            'api_v2_auth_local' => [
                'requestBody' => [
                    'uuid' => 'user-uuid-example',
                    'password' => 'user-password-example',
                    'turnstile_token' => 'valid_test_token',
                    'twoFACode' => '02YZR88R'
                ],
                'description' => 'This endpoint authenticates a user using their UUID, password, 
                and a CAPTCHA token. Platform can require the authentication with Two-Factor, 
                the twoFACode parameter will be asked based on the TWO_FACTOR_AUTH_STATUS setting.',
                'responses' => [
                    200 => [
                        'Authenticated user details and JWT token'
                    ],
                    400 => [
                        'CAPTCHA validation failed',
                        'Missing required fields: uuid, password or turnstile_token',
                        'Missing required configuration setting: TWO_FACTOR_AUTH_STATUS',
                        'Invalid json format',
                        'Invalid user provided. Please verify the user data'
                    ],
                    401 => [
                        'Two-Factor Authentication is active for this account. 
                        Please ensure you provide the correct authentication code.',
                        'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                        'Two-Factor Authentication it\'s required for authentication on the portal.
                         Please visit DOMAIN to set up 2FA and secure your account.',
                        'Invalid credentials'
                    ],
                    403 => [
                        'User account is not verified!',
                        'User account is banned from the system!',
                        'Your request cannot be processed at this time due to a pending action. 
                        If your account is active, re-login to complete the action',
                    ],
                    500 => [
                        'An error occurred: Generic server-side error.',
                        'JWT key files are missing. Please ensure both private and public keys exist.',
                    ]
                ],

            ],
            'api_v2_auth_saml' => [
                'requestBody' => [],
                'description' => 'This endpoint authenticates a user using their SAML response. 
                If the user is not found in the database, a new user will be created based on the SAML assertion. 
                The response includes user details along with a JWT token if authentication is successful. 
                Also if the platform requires authentication with Two-Factor, the twoFACode parameter will 
                be asked based on the TWO_FACTOR_AUTH_STATUS setting.',
                'responses' => [
                    200 => [
                        'Registration successful. Please check your email for further instructions',
                    ],
                    400 => [
                        'SAML Response not found',
                        'Invalid user provided. Please verify the user data',
                    ],
                    401 => [
                        'Unable to validate SAML assertion',
                        'Authentication Failed',
                        'Two-Factor Authentication is active for this account. 
                        Please ensure you provide the correct authentication code.',
                        'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                        'Two-Factor Authentication it\'s required for authentication on the portal. 
                        Please visit DOMAIN to set up 2FA and secure your account.'
                    ],
                    403 => [
                        'The provided IDP Entity is invalid or does not match the expected configuration.',
                        'The provided certificate is invalid or does not match the expected configuration.',
                        'User account is not verified!',
                        'User account is banned from the system!',
                    ],
                    500 => [
                        'An error occurred: Generic server-side error.',
                        'JWT key files are missing. Please ensure both private and public keys exist.',
                    ]
                ],
            ],
            'api_v2_auth_google' => [
                'requestBody' => [
                    'code' => '4/0AdKgLCxjQ74mKAg9vs_f7PuO99DR',
                    'twoFACode' => '02YZR88R'
                ],
                'description' => 'This endpoint authenticates a user using their Google account. 
                A valid Google OAuth authorization code is required. If the user is successfully authenticated,
                 user details and a JWT token will be returned. Also if the platform requires authentication with 
                 Two-Factor, the twoFACode parameter will be asked based on the TWO_FACTOR_AUTH_STATUS setting.',
                'responses' => [
                    200 => [
                        'Authenticated user details and JWT token'
                    ],
                    400 => [
                        'Invalid JSON format',
                        'Missing authorization code!',
                        'This code is not associated with a google account!',
                        'Invalid user provided. Please verify the user data',
                    ],
                    401 => [
                        'Two-Factor Authentication is active for this account. 
                        Please ensure you provide the correct authentication code.',
                        'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                        'Two-Factor Authentication it\'s required for authentication on the portal.
                         Please visit DOMAIN to set up 2FA and secure your account.',
                    ],
                    403 => [
                        'User account is not verified!',
                        'User account is banned from the system!',
                        'our email domain is not allowed to use this platform!',
                    ],
                    500 => [
                        'An error occurred: Generic server-side error.',
                        'JWT key files are missing. Please ensure both private and public keys exist.',
                    ]
                ],
            ],
            'api_v2_auth_microsoft' => [
                'requestBody' => [
                    'code' => '0.AQk6Lf2I2XGhQkWlU8gBp0KmxeNn2KTcbsJh.8Qt3OeYCB4sQ2FHo',
                    'twoFACode' => '02YZR88R'
                ],
                'description' => 'This endpoint authenticates a user using their Microsoft account. 
                A valid Microsoft OAuth authorization code is required. If the user is successfully authenticated, 
                user details and a JWT token will be returned. Also if the platform requires authentication with 
                Two-Factor, the twoFACode parameter will be asked based on the TWO_FACTOR_AUTH_STATUS setting.',
                'responses' => [
                    200 => [
                        'Authenticated user details and JWT token',
                    ],
                    400 => [
                        'Invalid JSON format',
                        'Missing authorization code!',
                        'This code is not associated with a microsoft account!',
                        'Invalid user provided. Please verify the user data',
                    ],
                    401 => [
                        'Two-Factor Authentication is active for this account. 
                        Please ensure you provide the correct authentication code.',
                        'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                        'Two-Factor Authentication it\'s required for authentication on the portal. 
                        Please visit DOMAIN to set up 2FA and secure your account.',
                    ],
                    403 => [
                        'User account is not verified!',
                        'User account is banned from the system!',
                        'our email domain is not allowed to use this platform!',
                    ],
                    500 => [
                        'An error occurred: Generic server-side error.',
                        'JWT key files are missing. Please ensure both private and public keys exist.',
                    ]
                ]

            ],
            'api_v2_capport_json' => [
                'requestBody' => [],
                'description' => 'Returns JSON metadata for the Captive Portal (CAPPORT) configuration.',
                'responses' => [
                    200 => [
                        'Successful response with CAPPORT metadata.',
                    ],
                    404 => [
                        'CAPPORT is not enabled'
                    ]
                ]
            ],
            'api_v2_config_settings' => [
                'description' => 'This endpoint returns public values from the Setting entity and environment 
                variables categorized by platform and provider.',
                'requestBody' => [],
                'responses' => [
                    200 => [
                        'Configuration settings retrieved successfully'
                    ]
                ]
            ],
            'api_v2_get_current_user' => [
                'requestBody' => [],
                'description' => 'This endpoint returns the details of the currently authenticated user.',
                'isProtected' => true,
                'responses' => [
                    200 => [
                        'User details retrieved successfully'
                    ],
                    401 => [
                        'JWT Token not found!',
                        'JWT Token is invalid!',
                        'JWT Token is expired!',
                    ],
                    403 => [
                        'Unauthorized - You do not have permission to access this resource.',
                        'User account is not verified!',
                        'User account is banned from the system!',
                    ],
                ]
            ],
            'api_v2_config_profile_android' => [
                'requestBody' => [
                    'public_key' => '-----BEGIN PUBLIC KEY-----\\n<RSA_PUBLIC_KEY>\\n-----END PUBLIC KEY-----'
                ],
                'description' => 'This endpoint retrieves the profile configuration for Android, including 
                a user\'s radius profile data, encrypted password, and other relevant settings for the Android
                 application.',
                'isProtected' => true,
                'responses' => [
                    200 => [
                        'Profile configuration for Android successfully retrieved'
                    ],
                    400 => [
                        'Invalid or missing public key'
                    ],
                    401 => [
                        'JWT Token is invalid!'
                    ],
                    403 => [
                        'Unauthorized access!'
                    ],
                    500 => [
                        'Failed to encrypt the password',
                    ]
                ]
            ],
            'api_v2_config_profile_ios' => [
                'requestBody' => [
                    'public_key' => '-----BEGIN PUBLIC KEY-----\\n<RSA_PUBLIC_KEY>\\n-----END PUBLIC KEY-----'
                ],
                'description' => 'This endpoint retrieves the profile configuration for iOS, including a user\'s radius
                 profile data, encrypted password, and other relevant settings for the iOS application.',
                'isProtected' => true,
                'responses' => [
                    200 => [
                        'Profile configuration for iOS successfully retrieved',
                    ],
                    400 => [
                        'Invalid or missing public key',
                    ],
                    401 => [
                        'JWT Token is invalid!'
                    ],
                    403 => [
                        'Unauthorized access!'
                    ],
                    500 => [
                        'Failed to encrypt the password',
                    ]
                ]
            ],
            'api_v2_auth_local_register' => [
                'requestBody' => [
                    'email' => 'user@example.com',
                    'password' => 'strongpassword',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint registers a new user using their email and password, 
                with CAPTCHA validation via the Turnstile token. It handles user creation, password hashing, 
                and CAPTCHA verification. If the user already exists, it returns a conflict error.',
                'responses' => [
                    200 => [
                        'Registration successful. Please check your email for further instructions',
                    ],
                    400 => [
                        'Invalid email format.',
                        'Invalid JSON format',
                        'Missing required fields: email, password or turnstile_token',
                        'CAPTCHA validation failed'
                    ],
                ]
            ],
            'api_v2_auth_local_reset' => [
                'requestBody' => [
                    'email' => 'user@example.com',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint triggers a password reset for a local auth account. 
                The user must provide their email and a CAPTCHA validation token. The endpoint verifies if the 
                user has an external auth with "PortalAccount" and "EMAIL" providerId, then proceeds with the 
                password reset if the conditions are met.',
                'responses' => [
                    200 => [
                        'Password reset email sent successfully',
                    ],
                    400 => [
                        'Invalid email format.',
                        'Invalid JSON format',
                        'Missing required fields: email or turnstile_token',
                        'CAPTCHA validation failed'
                    ],
                ]
            ],
            'api_v2_auth_sms_register' => [
                'requestBody' => [
                    'country_code' => 'PT',
                    'phone_number' => '1234567890',
                    'password' => 'strongpassword',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint registers a new user using their phone number and validates the
                 request with a CAPTCHA token.',
                'responses' => [
                    200 => [
                        'SMS User Account Registered Successfully. A verification code has been sent to your phone.',
                    ],
                    400 => [
                        'CAPTCHA validation failed',
                        'Missing required fields: country code, phone number, password, or turnstile_token',
                        'Invalid phone number format or country code.',
                        'Invalid json format',
                    ],
                    500 => [
                        'Failed to send SMS',
                        'User registered but SMS could not be sent.',
                    ]
                ]
            ],
            'api_v2_auth_sms_reset' => [
                'requestBody' => [
                    'country_code' => 'PT',
                    'phone_number' => '1234567890',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint sends an SMS with a new password and verification code if the user 
                has a valid PortalAccount and has not exceeded SMS request limits. The endpoint also enforces the 
                time interval between requests and limits the number of attempts allowed.',
                'responses' => [
                    200 => [
                        'Successfully sent the SMS with a new password and verification code',
                    ],
                    400 => [
                        'CAPTCHA validation failed',
                        'Missing required fields: country code, phone number, turnstile_token',
                        'Invalid phone number format or country code.',
                        'Invalid JSON format',
                    ],
                    500 => [
                        'An unexpected error occurred while processing the request',
                    ]
                ]

            ],
            'api_v2_turnstile_html_android' => [
                'requestBody' => [
                    'success' => true,
                    'data' => '<html><body><h1>Turnstile Configuration</h1><p>This is the required HTML 
configuration for the Android App.</p></body></html>'
                ],
                'description' => 'This endpoint serves the public HTML configuration required for the Android 
                App to integrate with the Turnstile feature.',
                'responses' => [
                    200 => [
                        'Turnstile HTML configuration retrieved successfully',
                    ],
                    404 => [
                        'HTML file not found.',
                    ]
                ]
            ],
            'api_v2_twoFA_request' => [
                'requestBody' => [
                    'uuid' => 'user-uuid-example',
                    'password' => 'user-password-example',
                    'turnstile_token' => 'valid_test_token'
                ],
                'description' => 'This endpoint provides Two-Factor Authentication code only for portal accounts.
                 To be able to request a authentication code the account needs to have setup a 2fa with email or SMS.',
                'responses' => [
                    200 => [
                        'Requested two-factor authentication token',
                    ],
                    400 => [
                        'CAPTCHA validation failed',
                        'Missing required fields: uuid, password or turnstile_token',
                        'Missing required configuration setting: TWO_FACTOR_AUTH_RESEND_INTERVAL 
                        TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS',
                        'Invalid json format',
                        'Invalid credentials'
                    ],
                    401 => [
                        'Invalid credentials',
                        'Invalid credentials'
                    ],
                    403 => [
                        'User account is not verified!',
                        'User account is banned from the system!',
                        'Invalid account type. Please only use email/phone number accounts from the portal',
                        'Invalid Two-Factor Authentication configuration Please ensure that 2FA is set up using 
                        either email or SMS for this account',
                        'The Two-Factor Authentication (2FA) configuration is incomplete. Please set up 2FA using
                         either email or SMS',
                        'Your request cannot be processed at this time due to a pending action. If your account is
                         active, re-login to complete the action',
                    ],
                    429 => [
                        'You need to wait %d seconds before asking for a new code.',
                        'Too many attempts. You have exceeded the limit of %d attempts. 
                        Please wait %d minutes before trying again.',
                        'Too many validation attempts. You have exceeded the limit of %d attempts. 
                        Please wait %d minute(s) before trying again.',
                    ]
                ]

            ],
            'api_v2_user_account_deletion' => [
                'requestBody' => [
                    'password' => 'user-password-example'
                ],
                'description' => 'This endpoint deletes the currently authenticated user account. 
                Depending on the authentication method, the request body may require a password (Portal Account), 
                a SAMLResponse (SAML), or an authorization code (Google/Microsoft). 
                The request verifies the provided authentication details before performing the account deletion.',
                'isProtected' => true,
                'responses' => [
                    200 => [
                        'User Account was deleted successfully.',
                    ],
                    400 => [
                        'Invalid data: Missing required fields.',
                        'Invalid JSON format',
                    ],
                    401 => [
                        'Invalid Request: JWT Token is invalid!',
                        'Invalid credentials: The provided password is incorrect.',
                        'Authentication Failed: Unable to validate SAML assertion.',
                        'Authentication Failed: Invalid or expired authorization code.',
                    ],
                    403 => [
                        'Unauthorized - You do not have permission to access this resource.',
                        'Unauthorized: The SAML assertion email does not match the user account email.',
                        'The configured IDP Entity ID does not match the expected value. Access denied.',
                        'Invalid Two-Factor Authentication configuration. Please ensure that 2FA is set up 
                        using either email or SMS for this account.',
                        'The Two-Factor Authentication (2FA) configuration is incomplete. Please set up 2FA 
                        using either email or SMS.',
                        'Invalid account type. Please only use email/phone number accounts from the portal.',
                    ],
                    404 => [
                        'Invalid Account: User account not found.',
                        'Required data from the external service could not be located.',
                    ],
                    500 => [
                        'An error occurred while deleting the user.',
                        'An error occurred while communicating with an external service.',
                        'An unexpected error occurred. Please try again later.',
                    ]
                ]
            ]
        ];

        if ($version === ApiVersion::API_V1->value) {
            return $apiResponseV1;
        }
        return $apiResponseV2;
    }

    public function getCommonResponses(): array
    {
        return [
            400 => [
                'Invalid JSON format',
                'Invalid data: Missing required fields.',
                'CAPTCHA validation failed',
            ],
            401 => [
                'JWT Token not found!',
                'JWT Token is expired!',
            ],
            403 => [
                'JWT Token is invalid!',
            ],
            500 => [
                'Internal Server Error',
            ],
        ];
    }
}
