# API Endpoints

This document provides an overview of the key API endpoints introduced in this project. Each endpoint is designed to
improve user authentication and management processes, with integrated CAPTCHA validation to ensure security.

## User Account Deletion

This endpoint allows users authenticated with a valid JWT to permanently delete their accounts. The request requirements
vary based on the authentication provider:

- **Portal Account**: Requires the account password in the request body.
- **SAML**: Requires the account's `SAMLResponse`.
- **Google/Microsoft**: Requires the authentication code.

For external providers, the endpoint performs a simulated authentication with the provider to validate and confirm
account deletion.

## Turnstile Configuration

- **Retrieve an empty HTML file for Turnstile configuration for Android App integration**
    - The HTML serves as a base configuration page required for the Turnstile integration.
    - This content is specifically designed for use in the public Android Turnstile functionality.
    - Includes placeholder elements for Android-specific app setup.

## Capport Configuration

- **Retrieves a JSON metadata for the Captive Portal (CAPPORT) configuration.**
    - This metadata enables admins to configure enforced messages when a user connects to a specific SSID
      associated with a profile on any access point. It ensures consistent messaging across all access
      points for that network.

## Setting

- **Public Settings Configuration**
    - Returns public values from the Setting entity and environment variables.
    - Data is categorized by platform and provider.

## Profile Configuration

- **Retrieves profile configuration for Android/iOS including the following data**
    - User Radius Profile Data.
    - Encrypted Radius Password with RSA
    - Other important settings for a profile generation (Domain Name, Operator Name, Radius TLS Name, etc...)

## User

- **Retrieve Current Authenticated User**
    - Returns details of the currently authenticated user.

## User Auth

- **Local Authentication**
    - Authenticates a user using their UUID and password.
    - Requires a valid Turnstile CAPTCHA token.

- **SAML Authentication**
    - Authenticates a user using their SAML response.
    - If the user is not found, a new user is created based on the SAML assertion.
    - Successful authentication returns user details and a JWT token.

- **Google Authentication**
    - Authenticates a user using their Google account ID.

- **Microsoft Authentication**
    - Authenticates a user using their Microsoft Azure account ID.

- **Two-Factor Authentication code request**
    - Request a new 2FA code for only portal accounts with two-factor configured with email or SMS.

## User Auth Register

- **Local Registration**
    - Register a new user via local authentication using their email.
    - Validates the request with a Turnstile CAPTCHA token.

- **SMS Registration**
    - Register a new user via SMS authentication using their phone number.
    - Requires Turnstile CAPTCHA token validation.

## User Auth Reset

- **Local Password Reset**
    - Triggers a password reset for a local authentication account.
    - Verifies external authentication with "PortalAccount" and "EMAIL" providerId before proceeding.
    - Requires Turnstile CAPTCHA token validation.

- **SMS Password Reset**
    - Sends an SMS with a new verification code for password reset.
    - Checks if the user has a valid PortalAccount and ensures SMS request limits and time intervals are respected.
    - Requires Turnstile CAPTCHA token validation.

### Notes

- Most of the endpoints above are integrated with CAPTCHA validation to increase security.
- Please refer to the [API documentation](api/index.html) generated with OpenAPI via Swagger UI for detailed usage
  instructions and examples.
