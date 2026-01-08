<?php

return [
    'api_v3_twoFA_validate' => [
        'routePrefix' => '/api/v3/twoFA/validate',
        'section' => 'TwoFactor',
        'description' => 'This endpoint validates a 2FA code (email, SMS, or TOTP). 
    The client must send a valid JWT Bearer token in the Authorization header, 
    along with the 2FA type and confirmation code in the request body. 
    If the code is valid, 2FA is enabled for that method.',
        'isProtected' => true,
        'requestBody' => [
            'type' => 'email or sms or topt',
            'code' => '123456'
        ],
        'responses' => [
            200 => [
                'default' => json_decode(
                    '{
                    "success": true,
                    "data": {
                        "message": "Two Factor authentication validated successfully!"
                    }
                }',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
            ],
            400 => [
                'Invalid JSON format',
                'Missing required body fields'
            ],
            401 => [
                'JWT Token is invalid!',
                'User account is not verified.'
            ],
            // phpcs:disable Generic.Files.LineLength.TooLong
            403 => [
                'Invalid code',
                'User account is banned from the system.',
                "Your request cannot be processed at this time due to a pending action. If your account is active, re-login to complete the action.",
                'Unauthorized - You do not have permission to access this resource'
            ],
            // phpcs:enable
            500 => [
                'Unexpected server error occurred'
            ]
        ]
    ],
    'api_v3_twoFA_enable' => [
        'routePrefix' => '/api/v3/twoFA/{type}',
        'section' => 'TwoFactor',
        'requestBody' => [
            'uuid' => 'user-uuid-example',
            'password' => 'user-password-example',
            'turnstile_token' => 'valid_test_token',
        ],
        'description' => 'Enables Two-Factor Authentication (2FA) for the authenticated user. 
        Supports ("totp", "email", "sms"). Requires a valid JWT Bearer token.',
        'responses' => [
            200 => [
                'totp_example' => json_decode(
                    '{
                    "success": true,
                    "data": {
                        "message": "Two Factor TOTP Secret generated successfully",
                        "totpId": "ABCDEF123456"
                    }
                  }',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
                'email_sms_example' => json_decode(
                    '{
                    "success": true,
                    "data": {
                        "message": "Two Factor Code sent to: user@example.com"
                    }
                }',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
            ],
            400 => [
                'description' => 'User does not have a valid email or phone to send the code.',
                'example' => json_decode(
                    '{
                    "message": "Code not sent, the user does not have a valid method."
                }',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
            ],
            401 => [
                'description' => 'Invalid, missing or expired JWT token OR user is not verified.',
                'examples' => [
                    'invalid_token' => json_decode(
                        '{
                        "message": "JWT Token is invalid!"
                    }',
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    ),
                    'user_not_verified' => json_decode(
                        '{
                        "message": "User account is not verified."
                    }',
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    ),
                ]
            ],
            403 => [
                'description' => 'User is authenticated but not allowed to access the resource.',
                'examples' => [
                    'not_authenticated' => json_decode(
                        '{
                        "message": "Unauthorized - You do not have permission to access this resource"
                    }',
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    ),
                    'user_banned' => json_decode(
                        '{
                        "message": "User account is banned from the system."
                    }',
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    ),
                    // phpcs:disable Generic.Files.LineLength.TooLong
                    'pending_action' => json_decode(
                        '{
                        "message": "Your request cannot be processed at this time due to a pending action. If your account is active, re-login to complete the action."
                    }',
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    ),
                    // phpcs:enable
                ],
            ],
            500 => [
                'description' => 'Internal error while generating 2FA code or TOTP secret.',
                'examples' => [
                    'db_error',
                    'unexpected_error'
                ],
            ],
        ],
    ],
    'api_v3_auth_refresh' => [
        'routePrefix' => '/api/v3/auth/refresh',
        'section' => 'Auth',
        'requestBody' => [
            'current_token' => 'currentToken',
        ],
        'description' => 'Refreshes the JWT token for an authenticated user.
The client must send the current valid JWT in the request body as "current_token".',
        'isProtected' => true,
        'responses' => [
            200 => [
                'default' => json_decode(
                    '{
                    "success": true,
                    "data": {
                        "auth_token": "newAuthToken"
                    }
                }',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
            ],
            400 => [
                'Missing or invalid request body',
                'The current_token is required'
            ],
            401 => [
                'Invalid token',
            ],
            500 => [
                'Token generation failed',
                'JWT key files are missing. Please ensure both private and public keys exist.'
            ]
        ],
    ],
    'api_v3_auth_local' => [
        'routePrefix' => '/api/v3/auth/local',
        'section' => 'Auth',
        'requestBody' => [
            'uuid' => 'user-uuid-example',
            'password' => 'user-password-example',
            'turnstile_token' => 'valid_test_token',
            'twoFACode' => '02YZR88R'
        ],
        'description' => 'This endpoint authenticates a user using their UUID, password, 
        and a CAPTCHA token. Platform can require the authentication with Two-Factor, 
        the twoFACode parameter will be asked based on the TWO_FACTOR_AUTH_STATUS setting.
        When the setting LOGIN_WITH_UUID_ONLY is enabled:
        The authentication process does not require a password. Instead, a success message is returned
        and the platform will send a login link via email or phone number.',
        'responses' => [
            200 => [
                'default' => json_decode(
                    '{
                    "success": true,
                    "data": {
                        "uuid": "test@example.com",
                        "email": "test@example.com",
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
                ),
                // phpcs:disable Generic.Files.LineLength.TooLong
                'uuid_only_mode' => json_decode(
                    '{
                                "success": true,
                                "data": {
                                    "message": "Authentication request sent. Please check your email or phone for the login link."
                                }
                            }',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
                // phpcs:enable
            ],
            400 => [
                'CAPTCHA validation failed',
                'Missing required fields: uuid, password or turnstile_token',
                'Missing required configuration setting: TWO_FACTOR_AUTH_STATUS',
                'Invalid json format',
                'Invalid user provided. Please verify the user data'
            ],
            401 => [
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.',
                // phpcs:enable
                'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
                // phpcs:enable
                'Invalid credentials'
            ],
            403 => [
                'User account is not verified!',
                'User account is banned from the system!',
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Your request cannot be processed at this time due to a pending action. If your account is active, re-login to complete the action',
                // phpcs:enable
            ],
            500 => [
                'An error occurred: Generic server-side error.',
                'JWT key files are missing. Please ensure both private and public keys exist.',
            ]
        ],
    ],
    'api_v3_auth_saml' => [
        'routePrefix' => '/api/v3/auth/saml',
        'section' => 'Auth',
        'requestBody' => [
            'SAML Account' => [
                'SAMLResponse' => 'samlResponseExample'
            ],
        ],
        'description' => 'This endpoint authenticates a user using their SAML response. 
                If the user is not found in the database, a new user will be created based on the SAML assertion. 
                The response includes user details along with a JWT token if authentication is successful. 
                Also if the platform requires authentication with Two-Factor, the twoFACode parameter will 
                be asked based on the TWO_FACTOR_AUTH_STATUS setting.',
        'responses' => [
            200 => [
                json_decode(
                    '{
                            "success": true,
                            "data": {
                                "uuid": "test@example.com",
                                "email": "test@example.com",
                                "roles": ["ROLE_USER"],
                                "first_name": null,
                                "last_name": null,
                                "user_external_auths": [
                                    {
                                        "provider": "SAML Account",
                                        "provider_id": "saml_account_name"
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
                'SAML Response not found',
                'Invalid user provided. Please verify the user data',
            ],
            401 => [
                'Unable to validate SAML assertion',
                'Authentication Failed',
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.',
                // phpcs:enable
                'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.'
                // phpcs:enable
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
    'api_v3_auth_google' => [
        'routePrefix' => '/api/v3/auth/google',
        'section' => 'Auth',
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
                json_decode(
                    '{
                            "success": true,
                            "data": {
                                "uuid": "test@example.com",
                                "email": "test@example.com",
                                "roles": ["ROLE_USER"],
                                "first_name": null,
                                "last_name": null,
                                "user_external_auths": [
                                    {
                                        "provider": "Google Account",
                                        "provider_id": "google_id_example"
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
                'Invalid JSON format',
                'Missing authorization code!',
                'This code is not associated with a google account!',
                'Invalid user provided. Please verify the user data',
            ],
            401 => [
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.',
                // phpcs:enable
                'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
                // phpcs:enable
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
    'api_v3_auth_microsoft' => [
        'routePrefix' => '/api/v3/auth/microsoft',
        'section' => 'Auth',
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
                json_decode(
                    '{
                            "success": true,
                            "data": {
                                "uuid": "test@example.com",
                                "email": "test@example.com",
                                "roles": ["ROLE_USER"],
                                "first_name": null,
                                "last_name": null,
                                "user_external_auths": [
                                    {
                                        "provider": "Microsoft Account",
                                        "provider_id": "microsoft_id_example"
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
                'Invalid JSON format',
                'Missing authorization code!',
                'This code is not associated with a microsoft account!',
                'Invalid user provided. Please verify the user data',
            ],
            401 => [
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.',
                // phpcs:enable
                'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
                // phpcs:enable
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
    'api_v3_capport_json' => [
        'routePrefix' => '/api/v3/capport/json',
        'section' => 'Config',
        'requestBody' => [],
        'description' => 'Returns JSON metadata for the Captive Portal (CAPPORT) configuration.',
        'responses' => [
            200 => [
                json_decode(
                    '{
                                  "captive": false,
                                  "user-portal-url": "https://example.com/",
                                  "venue-info-url": "https://openroaming.org/"
                                }',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            ],
            404 => [
                'CAPPORT is not enabled'
            ]
        ]
    ],
    'api_v3_config_settings' => [
        'routePrefix' => '/api/v3/config',
        'section' => 'Config',
        'description' => 'This endpoint returns public values from the Setting entity and environment 
                variables categorized by platform and provider.',
        'requestBody' => [],
        'responses' => [
            200 => [
                json_decode(
                    '{
                          "success": true,
                          "data": {
                            "platform": {
                              "PLATFORM_MODE": "Live",
                              "USER_VERIFICATION": true,
                              "TURNSTILE_CHECKER": true,
                              "CONTACT_EMAIL": "support@example.com",
                              "TOS": "LINK",
                              "PRIVACY_POLICY": "LINK",
                              "TWO_FACTOR_AUTH_STATUS": "NOT_ENFORCED"
                            },
                            "auth": {
                              "AUTH_METHOD_SAML_ENABLED": true,
                              "AUTH_METHOD_GOOGLE_LOGIN_ENABLED": true,
                              "AUTH_METHOD_MICROSOFT_LOGIN_ENABLED": true,
                              "AUTH_METHOD_REGISTER_ENABLED": true,
                              "AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED": true,
                              "AUTH_METHOD_SMS_REGISTER_ENABLED": true
                            },
                            "turnstile": {
                              "TURNSTILE_KEY": "example_turnstile_key"
                            },
                            "google": {
                              "GOOGLE_CLIENT_ID": "example_google_client_id"
                            },
                            "microsoft": {
                              "MICROSOFT_CLIENT_ID": "example_microsoft_client_id"
                            },
                            "saml": {
                              "SAML_IDP_ENTITY_ID": "https://example.com/saml/metadata",
                              "SAML_IDP_SSO_URL": "https://example.com/saml/sso",
                              "SAML_IDP_X509_CERT": "MIIC...AB",
                              "SAML_SP_ENTITY_ID": "https://example.com/saml/sp"
                            }
                          }
                        }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
            ],
        ]
    ],
    'api_v3_get_current_user' => [
        'routePrefix' => '/api/v3/user',
        'section' => 'User',
        'requestBody' => [],
        'description' => 'This endpoint returns the details of the currently authenticated user.',
        'isProtected' => true,
        'responses' => [
            200 => [
                json_decode(
                    '{
                                  "success": true,
                                  "data": {
                                    "uuid": "apitest2@api.com",
                                    "email": "apitest2@api.com",
                                    "roles": [
                                      "ROLE_USER"
                                    ],
                                    "first_name": null,
                                    "last_name": null,
                                    "user_external_auths": [
                                      {
                                        "provider": "Portal Account",
                                        "provider_id": "Email"
                                      }
                                    ],
                                    "phone_number": null,
                                    "is_verified": true,
                                    "created_at": "2025-06-30T10:55:24+00:00",
                                    "forgot_password_request": null
                                  }
                                }',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
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
    'api_v3_config_profile_android' => [
        'routePrefix' => '/api/v3/config/profile/android',
        'section' => 'Config',
        'requestBody' => [
            'public_key' => '-----BEGIN PUBLIC KEY-----\\n<RSA_PUBLIC_KEY>\\n-----END PUBLIC KEY-----'
        ],
        'description' => 'This endpoint retrieves the profile configuration for Android, including 
                a user\'s radius profile data, encrypted password, and other relevant settings for the Android
                 application.',
        'isProtected' => true,
        'responses' => [
            200 => [
                json_decode(
                    '{
                              "success": true,
                              "data": {
                                "config_android": {
                                  "radiusUsername": "user123",
                                  "radiusPassword": "encrypted_password_here",
                                  "friendlyName": "My Android Profile",
                                  "fqdn": "example.com",
                                  "roamingConsortiumOis": [
                                    "5a03ba0000",
                                    "004096"
                                  ],
                                  "eapType": 21,
                                  "nonEapInnerMethod": "MS-CHAP-V2",
                                  "realm": "example.com"
                                }
                              }
                            }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
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
                'Encryption succeeded but no encrypted data was returned.'
            ]
        ]
    ],
    'api_v3_config_profile_ios' => [
        'routePrefix' => '/api/v3/config/profile/ios',
        'section' => 'Config',
        'requestBody' => [
            'public_key' => '-----BEGIN PUBLIC KEY-----\\n<RSA_PUBLIC_KEY>\\n-----END PUBLIC KEY-----'
        ],
        'description' => 'This endpoint retrieves the profile configuration for iOS, including a user\'s radius
                 profile data, encrypted password, and other relevant settings for the iOS application.',
        'isProtected' => true,
        'responses' => [
            200 => [
                json_decode(
                    '{
                              "success": true,
                              "data": {
                                "payloadIdentifier": "com.apple.wifi.managed.<random_payload_identifier>-2",
                                "payloadType": "com.apple.wifi.managed",
                                "payloadUUID": "<random_payload_identifier>-1",
                                "domainName": "example.com",
                                "EAPClientConfiguration": {
                                  "acceptEAPTypes": 21,
                                  "radiusUsername": "user123",
                                  "radiusPassword": "encrypted_password_here",
                                  "outerIdentity": "anonymous@example.com",
                                  "TTLSInnerAuthentication": "MSCHAPv2"
                                },
                                "encryptionType": "WPA2",
                                "roamingConsortiumOis": [
                                  "5A03BA0000",
                                  "004096"
                                ],
                                "NAIRealmNames": "example.com"
                              }
                            }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
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
                'Encryption succeeded but no encrypted data was returned.'
            ]
        ]
    ],
    'api_v3_auth_local_register' => [
        'routePrefix' => '/api/v3/auth/local/register',
        'section' => 'Auth',
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
                // phpcs:disable Generic.Files.LineLength.TooLong
                json_decode(
                    '{
                              "success": true,
                              "data": {
                                "message": "Registration successful. Please check your email for further instructions."
                              }
                            }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
                // phpcs:enable
            ],
            400 => [
                'Invalid email format.',
                'Invalid JSON format',
                'Missing required fields: email, password or turnstile_token',
                'CAPTCHA validation failed'
            ],
        ]
    ],
    'api_v3_auth_local_reset' => [
        'routePrefix' => '/api/v3/config/local/reset',
        'section' => 'Auth',
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
                // phpcs:disable Generic.Files.LineLength.TooLong
                json_decode(
                    '{
                              "success": true,
                              "data": {
                                "message": "If the email address exists in our system, we have sent you a new one to: user@example.com"
                              }
                            }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
                // phpcs:enable
            ],
            400 => [
                'Invalid email format.',
                'Invalid JSON format',
                'Missing required fields: email or turnstile_token',
                'CAPTCHA validation failed'
            ],
        ]
    ],
    'api_v3_auth_sms_register' => [
        'routePrefix' => '/api/v3/auth/sms/register',
        'section' => 'Auth',
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
                // phpcs:disable Generic.Files.LineLength.TooLong
                json_decode(
                    '{
                              "success": true,
                              "data": {
                                "message": "SMS User Account Registered Successfully. A verification code has been sent to your phone."
                              }
                            }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
                // phpcs:enable
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
    'api_v3_auth_sms_reset' => [
        'routePrefix' => '/api/v3/auth/sms/reset',
        'section' => 'Auth',
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
                // phpcs:disable Generic.Files.LineLength.TooLong
                json_decode(
                    '{
                                      "success": true,
                                      "data": {
                                        "success": "If the phone number exists, we have sent a new code to: Country Code: 351 National Number: 925544896."
                                      }
                                    }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
                // phpcs:enable
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
    'api_v3_turnstile_html_android' => [
        'routePrefix' => '/api/v3/turnstile/android',
        'section' => 'Config',
        'requestBody' => [
            'success' => true,
            'data' => '<html><body><h1>Turnstile Configuration</h1><p>This is the required HTML 
configuration for the Android App.</p></body></html>'
        ],
        'description' => 'This endpoint serves the public HTML configuration required for the Android 
                App to integrate with the Turnstile feature.',
        'responses' => [
            200 => [
                // phpcs:disable Generic.Files.LineLength.TooLong
                json_decode(
                    '{
                                      "success": true,
                                      "data": "<html><body><h1>Turnstile Configuration</h1><p>This is the required HTML configuration for the Android App.</p></body></html>"
                                    }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
                // phpcs:enable
            ],
            404 => [
                'HTML file not found.',
            ]
        ]
    ],
    'api_v3_turnstile_html_ios' => [
        'routePrefix' => '/api/v3/turnstile/ios',
        'section' => 'Config',
        'requestBody' => [
            'success' => true,
            'data' => '<html><body><h1>Turnstile Configuration</h1><p>This is the required HTML 
configuration for the IOS App.</p></body></html>'
        ],
        'description' => 'This endpoint serves the public HTML configuration required for the IOS 
                App to integrate with the Turnstile feature.',
        'responses' => [
            200 => [
                // phpcs:disable Generic.Files.LineLength.TooLong
                json_decode(
                    '{
                                      "success": true,
                                      "data": "<html><body><h1>Turnstile Configuration</h1><p>This is the required HTML configuration for the IOS App.</p></body></html>"
                                    }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
                // phpcs:enable
            ],
            404 => [
                'HTML file not found.',
            ]
        ]
    ],
    'api_v3_user_account_deletion' => [
        'routePrefix' => '/api/v3/userAccount/deletion',
        'section' => 'User',
        'requestBody' => [
            'Portal Account' => [
                'password' => 'user-password-example'
            ],
            'SAML Account' => [
                'SAMLResponse' => 'samlResponseExample'
            ],
            'Google Account' => [
                'code' => 'googleCodeExample'
            ],
            'Microsoft Account' => [
                'code' => 'microsoftCodeExample'
            ],

        ],
        'description' => 'This endpoint deletes the currently authenticated user account. 
                Depending on the authentication method, the request body may require a password (Portal Account), 
                a SAMLResponse (SAML), or an authorization code (Google/Microsoft). 
                The request verifies the provided authentication details before performing the account deletion.',
        'isProtected' => true,
        'responses' => [
            200 => [
                json_decode(
                    '{
                              "success": true,
                              "data": {
                                "message": "User with UUID \"test@example.com\" successfully deleted."
                              }
                            }',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                )
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
                // phpcs:disable Generic.Files.LineLength.TooLong
                'Invalid Two-Factor Authentication configuration. Please ensure that 2FA is set up using either email or SMS for this account.',
                'The Two-Factor Authentication (2FA) configuration is incomplete. Please set up 2FA using either email or SMS.',
                // phpcs:enable
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
