# 🌐︎ OpenRoaming Provisioning Portal

Welcome to the OpenRoaming Provisioning Portal! This repository provides a **reference implementation designed to
baseline the industry** on the necessary components for developing an enabler component of OpenRoaming.

## Why it was created?

The primary objective of the **OpenRoaming Provisioning Portal is to simplify the provisioning of identities using
Passpoint**, enabling an OpenRoaming network to support seamless Wi-Fi connectivity and enhance security for users
across various environments.

The goal is to **provide secure Wi-Fi access to everyone** without the need for repeated logins or unsecure/open
networks, making Wi-Fi connectivity for individuals and enterprises easier, quicker, and more user-friendly.

## How it works?

OpenRoaming is an **open standard developed to enable global, secure, and automatic Wi-Fi connectivity**. With
OpenRoaming, users can connect to Wi-Fi networks without being prompted for login credentials. Instead, it utilizes
digital certificates and secure authentication mechanisms to **ensure a seamless and secure connection experience**.
This technology allows users to quickly switch between Wi-Fi networks—such as public hotspots, venues, residencies,
corporate networks, and other places—without delays or multiple logins.

Each user is provided with a unique and secure profile that caters to their specific needs and preferences. The
OpenRoaming Provisioning Portal simplifies the setup and configuration process for network administrators, acting as a
key enabler for OpenRoaming technology.

For more information about OpenRoaming Technology please visit: https://openroaming.org

## 🛠️ Tools Used

These are some of the most important tools used on the development of this project:

- **PHP**: Open source general-purpose scripting language that is especially suited for web development.
- **Symfony Framework**: The core of the portal, the Symfony framework provides a solid and scalable base for web
  applications.
- **Twig Templating Engine**: Generates consistent, responsive views by separating logic.
- **MySQL Database**: Efficiently method to save and return user profiles and settings of the portal.
- **Docker**: Encapsulating the project in containers to improve deployment and compatibility.

### Prerequisites:

- Linux based system - Ubuntu 22.04 LTS (tested for the reference implementation)
- Knowledge about Linux OS (required to set up the project)
- Radius DB and a stack IDP prepared to use the portal
- Docker (required for running the application)
- Docker compose (responsible for managing multiple containers)
- Git (optional, if the user prefers to clone the repository)

### How to get the Project

There are two options to retrieve the project:

1. **Download Release Package**: Download the release package from the releases section on GitHub. This package contains
   only the required components to run the OpenRoaming Provisioning Portal,
   including `.env.sample`, `docker-compose.yml`, and other necessary files.

2. **Clone the Repository**: If the user is familiar with Git and want to access the complete source code, can clone the
   repository using the following command:

```bash
- git clone <repository-url>
```

## 📖 Features

This section explains the basic concepts behind each portal component.

### User Management:

- **List User** : View a list of all registered users in the system, can be filtered by all/only verified/only banned
  and sorted by creation date, among others.
- **Edit User Data** : Edit user information.
- **Search User**: Find users using various searching is email/uuid.
- **Delete User**: Remove user accounts from the system, **not permanently**.
- **Export Users Table** Can export all the user table content, this feature is disabled by default for legal and
  security reasons.

### Portal Management

All the present items can be customizable:

- **Show Customer Logo**
- **Customer Logo**
- **Openroaming Logo**
- **Wallpaper Image**
- **Page Title**
- **Welcome Text**
- **Welcome Description**
- **Additional Label**
- **Contact Email**

### Settings Management

- **Platform Status**
- **Terms and Policies**
- **Radius Configuration**
- **Authentication Methods**
- **LDAP Synchronization**
- **User Engagement**
- **SMS Configuration**

### Portal Statistics

This page shows data related to the user created on the portal

- **Devices**: Type of devices on the portal (Android, Windows, macOS, iOS)
- **Authentication**: Type of authentications present on the portal: (SAML, Google, Portal)
- **Portal with SMS or Email**: Shows data related to authentications on the portal (SMS & Email)
- **User Created in**: Shows data about creation of users in demo/live mode
- **User Management**: Shows data about the verification (verified/banned/need verification)

### Connectivity Statistics

This page shows data related to the hybrid machine

- **Authentication Attempts**: Shows number of attempts (Accepted/Rejected)
- **Session Time**: Shows the session time spent connected with a profile, of each user (Average/Total in hours)
- **Total of Traffic**: Shows the traffic passed between the freeradius and the user profile (Uploads/Downloads)
- **Realms Usage**: Number of devices connected using the realm from the portal
- **Total of Current Authentications** Shows the number of current users connected with a profile (This card is
  independent of the date filtering)

### OpenRoaming Portal API

This page shows data related to the endpoints in the project required for user authentication, management, and
configuration within the OpenRoaming Portal. It includes detailed descriptions of each endpoint, highlighting their
purpose, required inputs, and expected outputs.

Additionally, the documentation shows the necessary security measures,
such as CAPTCHA validation, that are integrated to protect user data and ensure secure interactions with the API.

Follow this link for more information on API documentation: [Api Guide](docs/APIGUI.md)

# ⚙️ Installation Guide

Follow this link for more information on installing this
project: [Installation Guide](docs/INSTALATION.md).

# Portal Overview & Baseline Operation

The objective is for the user to get familiarized with the project and its baseline features.

## Platform Mode (Demo or Live)

The project provides two modes: Platform mode set to **DEMO** or **LIVE**, each serving to different needs.

- **Platform Mode (Demo)**: When platform mode is set to DEMO, the system generates demo profiles based on the submitted
  email.
  This allows users to explore and test the portal's functionality without the need to create a user account. In demo
  mode, only "demo login" is displayed, and SAML and other login methods are disabled, regardless of other settings. A
  demo warning is also displayed, indicating that the system is in demo mode. **PLEASE DO NOT USE THIS IN PRODUCTION.**
  This mode can't be used because externally due to legal implications in this type of environment.

When this mode is activated, **it's not required** to verify the user account several times.

- **Platform Mode (Live)**: When platform mode is set to LIVE, profiles are generated based on
  individual user accounts inside the project. This offers a completely customized and secure Wi-Fi experience adapted
  to the interests and needs of each user. Users can set up accounts in production mode and use all available login
  methods, including SAML and Google authentication.

When this mode is activated, **it's required** to verify the account every time the user wants to download a profile
again, because it's a new demo account being generated on the portal.

Follow this link for a portal user interface overview: [Portal Guide](docs/PORTALGUI.md).

## 🔧 Environment Variables

The OpenRoaming Provisioning Portal utilizes environment variables for its configuration.
Below is an overview of the different variables and their functions:

- `APP_ENV`: This sets the environment mode for the Symfony application. It can be `dev` or `prod`.
- `APP_SECRET`: This is the application secret used by Symfony for encrypting cookies and generating CSRF tokens.
- `DATABASE_URL`: This is the connection string for the primary MySQL database. It should be in the
  format `mysql://user:pass@host:port/dbname`.
- `DATABASE_FREERADIUS_URL`: This is the connection string for the FreeRADIUS MySQL database, used for RADIUS related
  operations. It should be in the format `mysql://user:pass@host:port/dbname`.
- `MESSENGER_TRANSPORT_DSN`: This defines the transport (e.g., AMQP, Doctrine, etc.) that Symfony Messenger will use for
  dispatching messages. The value `doctrine://default?auto_setup=0` uses Doctrine DBAL with auto setup disabled.
- `MAILER_DSN`: This specifies the method of transport used to send emails with the Symfony Mailer component.
  Examples:
    ```dotenv
    # This are just a examples, please DO NOT USE IT
    # Example 1: Sending emails via SMTP (e.g., Gmail)
    MAILER_DSN=smtp://user:password@smtp.gmail.com:587?encryption=tls&auth_mode=login

    # Example 2: Using the default email transport (using PHP's mail() function)
    MAILER_DSN=mail://default
    ```
- `EMAIL_ADDRESS`: Entity of sends the emails to the users
- `SENDER_NAME`: Entity sender name
- `BUDGETSMS_API_URL`: This env manages the budget SMS link of the API, is not necessary to change this env.
- `EXPORT_USERS`: This env manages the operation to export all the **User table** content, this is disabled by default
  for legal and security reasons.
- `EXPORT_FREERADIUS_STATISTICS`: Manages the export of FreeRADIUS statistics from the admin page.

These two envs are for debugging purposes, they only should be used to control and manage reports from the portal.
`SENTRY_DSN`& `TRUSTED_PROXIES`.

- `ENABLE_DELETE_USERS_UI`: Shows a button on the UI, to be able to remove users from the
  portal.
  This action doesn't remove users, only encrypts the data with PGP (Pretty Good Privacy).

Please make sure to set up a **public_key** in (pgp_public_key/public_key.asc)
**do not create keys on the production server**.

### Google Authenticator Credentials

These credentials can be found on the Google Cloud Platform
by creating a new client_id & secret on the **credentials section**.
Follow this link for more instructions for how to get does items:
https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid

- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`

### 🔒 SAML Specific Settings

These variables are needed to set up the SAML Service Provider (SP) and Identity Provider (IdP):

- `SAML_IDP_ENTITY_ID`: This is the entity ID (URI) of the IdP.
- `SAML_IDP_SSO_URL`: This is the URL of the IdP's Single Sign-On (SSO) service.
- `SAML_IDP_X509_CERT`: This is the X509 certificate from the IdP, used for verifying SAML responses.
- `SAML_SP_ENTITY_ID`: This is the entity ID (URI) of the SP.
- `SAML_SP_ACS_URL`: This is the URL of the SP's Assertion Consumer Service (ACS), which processes SAML assertions from
  the IdP.

**Important**:
If you want to use this provider authentication on the project,
make sure to expose a SAML attribute on your IDP named
`samlUuid`,
to expose a unique id of the SAML account.
This property it's required to authenticate users if one of them doesn't have an email defined on the IDP.

### 👾 Turnstile Integration

These two are used to configure the Turnstile integration with the portal, to check and validate actual users.

- `TURNSTILE_KEY`: Stores the public key for Cloudflare Turnstile integration.
- `TURNSTILE_SECRET`: Holds the secret key for Cloudflare Turnstile integration.

For **testing** purposes with Cloudflare Turnstile, please use this
link: [Cloudflare Turnstile Testing](https://developers.cloudflare.com/turnstile/troubleshooting/testing/).

And for any **production deployment**, please follow the
link: [Cloudflare Turnstile Production Guide]( https://developers.cloudflare.com/turnstile/get-started/).

### 🌍 GeoLite GUI Documentation

For detailed instructions on the GeoLite GUI setup, operations, and usage, refer to
the [GeoLite GUI Guide](docs/GEOLITEGUI.md).

### 🕷️ API Platform

The following configurations are required for the API of the project.

- `CORS_ALLOW_ORIGIN`: Required to let the project know which domain is able to use the API

#### 🪙 Jwt Tokens

If you are planning using the API, please make sure to run the following command on the root folder of the container
project:

```bash
php bin/console lexik:jwt:generate-keypair
```

A **public** && **private** keys will be automatically generated. If you want to know where they will be placed, please
check the `.env.sample`.

- `JWT_SECRET_KEY`: The secret defined for the key.
- `JWT_PUBLIC_KEY`: The public key location.
- `JWT_PASSPHRASE`: The private key location.

### 🛠️ Settings Table

The OpenRoaming Provisioning Portal has a detailed "setting" table that allows you to customize the application to your
individual needs. Here's a rundown of several important variables and their functions:

1. `RADIUS_REALM_NAME`: The realm name for your RADIUS server.
2. `DISPLAY_NAME`: The name used on the profiles.
3. `PAYLOAD_IDENTIFIER`: The identifier for the payload used on the profiles.
4. `OPERATOR_NAME`: The operator name used on the profiles.
5. `DOMAIN_NAME`: The domain name used for the service.
6. `RADIUS_TLS_NAME`: The hostname of your RADIUS server used for TLS.
7. `NAI_REALM`: The realm used for Network Access Identifier (NAI).
8. `RADIUS_TRUSTED_ROOT_CA_SHA1_HASH`: The SHA1 hash of your RADIUS server's trusted root CA (Defaults to LetsEncrypt
   CA).

**IMPORTANT**: The LetsEncrypt CA's SHA1 hash is set as the default value.
This hash is important since it is needed
to validate the RADIUS server's certificate.

**Missing Values:** Please check that all crucial fields are fully filled if any values are missing.
Pay attention to the UUID field (domain of your portal).
It's critical to provide a unique UUID that differs from the default.
The Same uuid may result in conflicts between different portals, resulting in profile overrides.

If you use a different CA for your RADIUS server, you must replace this value with the SHA1 hash of your CA's root
certificate. **Connection errors** can happen if the right SHA1 hash is not provided.

1. `PLATFORM_MODE`: Live || Demo.
   When in Demo, only "demo login" is displayed, and SAML and other login
   methods are disabled regardless of other settings. A demo warning will also be displayed.
2. `USER_VERIFICATION`: ON || OFF.
   When it\'s ON it activates the email verification system.
   This system requires all
   the users to verify its own account before they download any profile.
3. `TURNSTILE_CHECKER`: ON || OFF.
   When it\'s ON, it activates the turnstile verification system.
   This system requires all
   the users to check and verify is session before creating an account.
   To prevent bots.
4. `API_STATUS`: Defines whether the API is enabled or disabled.

5. `PAGE_TITLE`: The title displayed on the webpage.
6. `CUSTOMER_LOGO_ENABLED`: Shows the customer logo on the landing page.
7. `CUSTOMER_LOGO`: The resource path or URL to the customer logo image.
8. `OPENROAMING_LOGO`: The resource path or URL to the OpenRoaming logo image.
9. `WALLPAPER_IMAGE`: The resource path or URL to the wallpaper image.
10. `WELCOME_TEXT`: The welcome text displayed on the user interface.
11. `WELCOME_DESCRIPTION`: The description text displayed under the welcome text.
12. `VALID_DOMAINS_GOOGLE_LOGIN`: Defines the valid domains to authenticate with Google, when it's empty, he lets anyone
    with a Google account login.
13. `VALID_DOMAINS_MICROSOFT_LOGIN`: Defines the valid domains to authenticate with Microsoft Azure, when it's empty, he
    lets anyone
    with a Microsoft account login.
14. `CONTACT_EMAIL`: The email address for contact inquiries.

15. `AUTH_METHOD_SAML_ENABLED`: Enable or disable SAML authentication method.
16. `AUTH_METHOD_SAML_LABEL`: The label for SAML authentication on the login page.
17. `AUTH_METHOD_SAML_DESCRIPTION`: The description for SAML authentication on the login page.
18. `AUTH_METHOD_GOOGLE_LOGIN_ENABLED`: Enable or disable Google authentication method.
19. `AUTH_METHOD_GOOGLE_LOGIN_LABEL`: The label for Google authentication button on the login page.
20. `AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION`: The description for Google authentication on the login page.
21. `AUTH_METHOD_MICROSOFT_LOGIN_ENABLED`: Enable or disable Google authentication method.
22. `AUTH_METHOD_MICROSOFT_LOGIN_LABEL`: The label for Google authentication button on the login page.
23. `AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION`: The description for Google authentication on the login page.
24. `AUTH_METHOD_REGISTER_METHOD_ENABLED`: Enable or disable Register authentication method.
25. `AUTH_METHOD_REGISTER_METHOD_LABEL`: The label for Register authentication button on the login page.
26. `AUTH_METHOD_REGISTER_METHOD_DESCRIPTION`: The description for Register authentication on the login page.
27. `AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED`: Enable or disable Login (email) authentication method.
28. `AUTH_METHOD_LOGIN_TRADITIONAL_LABEL`: The label for Login (email) authentication button on the login page.
29. `AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION`: The description for Login (email) authentication on the login page.
30. `AUTH_METHOD_SMS_REGISTER_ENABLED`: Enable or disable Login (SMS) authentication method.
31. `AUTH_METHOD_SMS_REGISTER_LABEL`: The label for Login (SMS) authentication button on the login page.
32. `AUTH_METHOD_SMS_REGISTER_DESCRIPTION`: The description for Login (SMS) authentication on the login page.

33. `SYNC_LDAP_ENABLED`: Enable or disable synchronization with LDAP.
34. `SYNC_LDAP_SERVER`: The LDAP server's URL.
35. `SYNC_LDAP_BIND_USER_DN`: The Distinguished Name (DN) used to bind to the LDAP server.
36. `SYNC_LDAP_BIND_USER_PASSWORD`: The password for the bind user on the LDAP server.
37. `SYNC_LDAP_SEARCH_BASE_DN`: The base DN used when searching the LDAP directory.
38. `SYNC_LDAP_SEARCH_FILTER`: The filter used when searching the LDAP directory.
    The placeholder `@ID` is replaced with the user's ID.

39. `PROFILES_ENCRYPTION_TYPE_IOS_ONLY`: Type of encryption defined for the creation of the profiles, for iOS only.
40. `CAPPORT_ENABLED`: Enable or disable Capport DHCP configuration.
41. `CAPPORT_PORTAL_URL`: Domain that is from the entity hosting the service.
42. `CAPPORT_VENUE_INFO_URL`: Domain where the user is redirected after clicking the DHCP notification.
43. `SMS_USERNAME`: Budget SMS Username.
44. `SMS_USER_ID`: Budget SMS User ID.
45. `SMS_HANDLE`: Budget SMS Handle hash.
46. `SMS_FROM`: Entity sending the SMS for the users.
47. `SMS_TIMER_RESEND`: Timer in minutes to make the user wait to resend a new SMS.

48. `TOS_LINK`: Terms and Conditions URL.
49. `PRIVACY_POLICY_LINK`: Privacy and Policy URL.
50. `USER_DELETE_TIME`: Time in hours to delete the unverified user.
51. `TIME_INTERVAL_NOTIFICATION`: Time in days to resend the notification when the profile is about to expire.
52. `PROFILE_LIMIT_DATE_SAML`: Time in days to disable profiles for SAML users with login.
53. `PROFILE_LIMIT_DATE_GOOGLE`: Time in days to disable profiles for users with GOOGLE login.
54. `PROFILE_LIMIT_DATE_MICROSOFT`: Time in days to disable profiles for users with MICROSOFT login.
55. `PROFILE_LIMIT_DATE_EMAIL`: Time in days to disable profiles for users with EMAIL login.
56. `PROFILE_LIMIT_DATE_SMS`: Time in days to disable profiles for users with SMS login.

#### With these environment variables, you can configure and customize various aspects of the project, such as database connections, SAML settings, login methods, and more.

For more information please contact: openroaming@wballiance.com
