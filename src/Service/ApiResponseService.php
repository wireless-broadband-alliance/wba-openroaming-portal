<?php

namespace App\Service;

use Symfony\Component\Routing\RouterInterface;

readonly class ApiResponseService
{
    public function __construct(
        private RouterInterface $router
    ) {}

    public function getRoutesByPrefix(string $prefix): array
    {
        $routes = $this->router->getRouteCollection();
        $filtered = [];
        $responses = $this->getResponseMetadata();

        foreach ($routes as $name => $route) {
            if (str_starts_with($route->getPath(), $prefix) && $route->getPath() !== $prefix) {
                $filtered[] = [
                    'name' => $name,
                    'path' => $route->getPath(),
                    'methods' => $route->getMethods(),
                    'responses' => $responses[$name] ?? [],
                ];
            }
        }

        return $filtered;
    }

    private function getResponseMetadata(): array
    {
        return [
            'api_v2_auth_local' => [
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
                    'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.',
                    'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                    'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
                    'Invalid credentials'
                ],
                403 => [
                    'User account is not verified!',
                    'User account is banned from the system!',
                    'Your request cannot be processed at this time due to a pending action. If your account is active, re-login to complete the action',
                ],
                500 => [
                    'An error occurred: Generic server-side error.',
                    'JWT key files are missing. Please ensure both private and public keys exist.',
                ]
            ],
            'api_v2_auth_saml' => [
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
                    'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.',
                    'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                    'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.'
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
            'api_v2_auth_google' => [
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
                    'Two-Factor Authentication is active for this account. Please ensure you provide the correct authentication code.',
                    'Two-Factor Authentication is ENFORCED FOR PORTAL accounts.',
                    'Two-Factor Authentication it\'s required for authentication on the portal. Please visit DOMAIN to set up 2FA and secure your account.',
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
            'api_v2_auth_microsoft' => [
                200 => [
                    'Authenticated user details and JWT token',
                ],
                400 => [
                    'Invalid JSON format',
                    'Missing authorization code!',
                    'This code is not associated with a microsoft account!',
                    'Invalid user provided. Please verify the user data',
                ],
            ],
            'api_v2_auth_local_register' => [
                200 => [
                    'Registration successful. Please check your email for further instructions',
                ],
                400 => [
                    'Invalid email format.',
                    'Invalid JSON format',
                    'Missing required fields: email, password or turnstile_token',
                    'CAPTCHA validation failed'
                ],
            ],
        ];
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
