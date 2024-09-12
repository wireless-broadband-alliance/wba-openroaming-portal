This document provides an overview of the key API endpoints introduced in this project. Each endpoint is designed to
improve user authentication and management processes, with integrated CAPTCHA validation to ensure security.

## API Endpoints

### Setting

- **Public Settings Configuration**
    - Returns public values from the Setting entity and environment variables.
    - Data is categorized by platform and provider.

### User

- **Retrieve Current Authenticated User**
    - Returns details of the currently authenticated user.

### User Auth

- **Local Authentication**
    - Authenticates a user using their UUID and password.
    - Requires a valid Turnstile CAPTCHA token.

- **SAML Authentication**
    - Authenticates a user using their SAML response.
    - If the user is not found, a new user is created based on the SAML assertion.
    - Successful authentication returns user details and a JWT token.

- **Google Authentication**
    - Authenticates a user using their Google account ID.

### User Auth Register

- **Local Registration**
    - Registers a new user via local authentication using their email.
    - Validates the request with a Turnstile CAPTCHA token.

- **SMS Registration**
    - Registers a new user via SMS authentication using their phone number.
    - Requires Turnstile CAPTCHA token validation.

### User Auth Reset

- **Local Password Reset**
    - Triggers a password reset for a local authentication account.
    - Verifies external authentication with "PortalAccount" and "EMAIL" providerId before proceeding.
    - Requires Turnstile CAPTCHA token validation.

- **SMS Password Reset**
    - Sends an SMS with a new verification code for password reset.
    - Checks if the user has a valid PortalAccount and ensures SMS request limits and time intervals are respected.
    - Requires Turnstile CAPTCHA token validation.

## Notes

- Most of the endpoints above are integrated with CAPTCHA validation to increase security.
- Please refer to the [API documentation](docs/index.html) generated with OpenAPI via Swagger UI for detailed usage
  instructions and examples.
