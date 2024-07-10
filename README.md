# 🌐︎ FreeOpenRoaming Provisioning Portal

OpenRoaming Provisioning Portal is a One-Stop Solution for Automated Device Authentication on Wireless
Networks! 🚀
The Portal improves the process of connecting to Wi-Fi in any area by creating a secure and
unique profile for each user.
With automatic device authentication, you can enjoy an easy, seamless and secure Wi-Fi experience.

## Why it was created?

This Portal was created with the objective of **simplifying Wi-Fi connectivity** and improving security for users in a
variety of contexts. We think that **everyone should have access to secure Wi-Fi** without having to log in several
times or use unsecure/open networks. Our goal is to make Wi-Fi connectivity for people and enterprises easier, quicker,
and more user-friendly.

## How it works?

By design, OpenRoaming is an open standard that has been developed to **enable globally,
secure, and automatic Wi-Fi connectivity**.

Users are able to connect to Wi-Fi networks using OpenRoaming without being ask for login credentials.
Instead, makes use
of **digital certificates and secure authentication mechanisms**.
This means that users can **switch quickly between
Wi-Fi networks, such as public hotspots, corporate networks, and other places, without delays or several logins**.

The technology ensures that each user is **provided with a unique and secure profile** that caters to their specific
needs and preferences.

The OpenRoaming Provisioning Portal acts as a **key enabler for OpenRoaming technology by simplifying the setup and
configuration process** for network administrators.

You are taking an **important step** toward **improving Wi-Fi connection** for your users by providing a safe and *
*user-friendly experience for everybody** by utilizing the OpenRoaming Provisioning Portal.

### Benefits

- **Seamless Wi-Fi Connectivity**: Say goodbye to manual login problems and connect to Wi-Fi networks with simplicity.
  📶
- **Improved Security**: Have peace of mind knowing that your personal information is safe with secure profile
  encryption. 🔒
- **Personalization**: Each user gets a unique profile tailored to their specific needs and preferences. 🎯
- **Scalability**: Because the portal is built for a high number of users and devices, it is excellent for both local
  and large-scale installations. 🌟

### Use Cases

- **Public Wi-Fi Hotspots**: Simplify the login method for users accessing Wi-Fi in coffee shops, airports, hotels, and
  other public locations. ☕️✈
- **Corporate Networks**: Facilitate employee onboarding and Wi-Fi access in workplaces while maintaining secure
  connectivity for all devices. 💼
- **Events and Conferences**: Provide seamless Wi-Fi access to attendees with personalized profiles, enhancing the
  overall event experience. 🎉

Whether you're our solution aims to exceed your expectations and make Wi-Fi connectivity better. Let's embark on this
journey together and redefine how we connect wirelessly! 🚀

## 📖 Features

### User Management:

- **List User** : View a list of all registered users in the system, can be filtered by all/only verified/ only banned
  and sorted by creation date, etc...
- **Edit User Data** : Edit user information: ![Edit_User_Admin](assets/wba_screenshots/edit_user_admin.png)
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

## 🛠️ Tools Used

These are some of the most important tools used on the development of this project.

- **PHP**: Open source general-purpose scripting language that is especially suited for web development.
- **Symfony Framework**: The core of the portal, the Symfony framework provides a solid and scalable base for web
  applications.
- **Twig Templating Engine**: Generates consistent, responsive views by separating logic.
- **MySQL Database**: Efficiently method to save and return user profiles and settings of the portal.
- **Docker**: Encapsulating the project in containers to improve deployment and compatibility.

## Getting Started - Setup Guide

In this guide, we'll lead you through the setup of the OpenRoaming Provisioning Portal step by step.

To understand how it works, you'll get basic knowledge behind each stage. You will possess a fully working automatic
device authentication for your wireless networks by the end of this guide. Let's get started! 🚀

### Prerequisites:

- Ubuntu 22.04 LTS
- Knowledge about Linux OS (required to setup the project)
- Radius db and a stack IDP prepared to use the portal
- Docker (required for running the application)
- Docker-compose (required for managing multiple containers)
- Node Js 16 or higher (required for building front-end assets)
- Git (optional, if you prefer to clone the repository)

### How to get the Project

You have two options to get the project:

1. **Download Release Package**: Download the release package from the releases section on GitHub. This package contains
   only the required components to run the OpenRoaming Provisioning Portal,
   including `.env.sample`, `docker-compose.yml`, and other necessary files.


2. **Clone the Repository**: If you're familiar with Git and want to access the complete source code, you can clone the
   repository using the following command:

```bash
- git clone <repository-url>
```

# ⚙️ Installation Guide

Please click this link if you want to install this project on your own. [Installation Guide](INSTALATION.md).

# How it Looks and How it Works?

Now we will show how the project looks, and give you some base information about how it works.

## Platform Mode Demo or Live

The project provides two modes: Platform mode set to **DEMO** or **LIVE**, each serving to different needs.

- **Platform Mode (Demo)**: When platform mode is set to DEMO, the system generates demo profiles based on the submitted
  email.
  This allows users to explore and test the portal's functionality without the need to create a user account. In demo
  mode, only "demo login" is displayed, and SAML and other login methods are disabled, regardless of other settings. A
  demo warning is also displayed, indicating that the system is in demo mode. **PLEASE DO NOT USE THIS IN PRODUCTION.**
  This mode can't be used because of legal specification in this type of environment.

When this mode is activated, **it's not required** to verify the user account several times.

- **Platform Mode (Live)**: On the other hand, when demo mode is set to FALSE, profiles are generated based on
  individual user accounts inside the project. This offers a completely customized and secure Wi-Fi experience adapted
  to the interests and needs of each user. Users can set up accounts in production mode and use all available login
  methods, including SAML and Google authentication.

When this mode is activated, **it's required** to verify the account every time the user wants to download a profile
again, because it's a new demo account being generated on the portal.

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
  for
  legal and security reasons.
- `EXPORT_FREERADIUS_STATISTICS`: Manages the export of FreeRADIUS statistics from the admin page.

These two envs are for debugging purposes, they only should be used to control and manage reports from the portal.
`SENTRY_DSN`& `TRUSTED_PROXIES`.

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

These last two are used to configure the Turnstile integration with the portal, to check and validate actual users.

- `TURNSTILE_KEY`: Stores the public key for Cloudflare Turnstile integration.
- `TURNSTILE_SECRET`: Holds the secret key for Cloudflare Turnstile integration.

For **testing** purposes with Cloudflare Turnstile, please use this
link: [Cloudflare Turnstile Testing](https://developers.cloudflare.com/turnstile/troubleshooting/testing/).

And for any **production deployment**, please follow the
link: [Cloudflare Turnstile Production Guide]( https://developers.cloudflare.com/turnstile/get-started/).

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

9. `PLATFORM_MODE`: Live || Demo.
   When demo, only "demo login" is displayed, and SAML and other login
   methods are disabled regardless of other settings. A demo warning will also be displayed.
10. `USER_VERIFICATION`: ON || OFF.
    When it\'s ON it activates the email verification system.
    This system requires all
    the users to verify its own account before they download any profile.
11. `TURNSTILE_CHECKER`: ON || OFF.
    When it\'s ON, it activates the turnstile verification system.
    This system requires all
    the users to check and verify is session before creating an account.
    To prevent bots.

12. `PAGE_TITLE`: The title displayed on the webpage.
13. `CUSTOMER_LOGO_ENABLED`: Shows the customer logo on the landing page.
14. `CUSTOMER_LOGO`: The resource path or URL to the customer logo image.
15. `OPENROAMING_LOGO`: The resource path or URL to the OpenRoaming logo image.
16. . `WALLPAPER_IMAGE`: The resource path or URL to the wallpaper image.
17. `WELCOME_TEXT`: The welcome text displayed on the user interface.
18. `WELCOME_DESCRIPTION`: The description text displayed under the welcome text.
19. `VALID_DOMAINS_GOOGLE_LOGIN`: Defines the valid domains to authenticate with Google, when it's empty, he lets anyone
    with a google account login
20. `CONTACT_EMAIL`: The email address for contact inquiries.

21. `AUTH_METHOD_SAML_ENABLED`: Enable or disable SAML authentication method.
22. `AUTH_METHOD_SAML_LABEL`: The label for SAML authentication on the login page.
23. `AUTH_METHOD_SAML_DESCRIPTION`: The description for SAML authentication on the login page.
24. `AUTH_METHOD_GOOGLE_LOGIN_ENABLED`: Enable or disable Google authentication method.
25. `AUTH_METHOD_GOOGLE_LOGIN_LABEL`: The label for Google authentication button on the login page.
26. `AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION`: The description for Google authentication on the login page.
27. `AUTH_METHOD_REGISTER_METHOD_ENABLED`: Enable or disable Register authentication method.
28. `AUTH_METHOD_REGISTER_METHOD_LABEL`: The label for Register authentication button on the login page.
29. `AUTH_METHOD_REGISTER_METHOD_DESCRIPTION`: The description for Register authentication on the login page.
30. `AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED`: Enable or disable Login (email) authentication method.
31. `AUTH_METHOD_LOGIN_TRADITIONAL_LABEL`: The label for Login (email) authentication button on the login page.
32. `AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION`: The description for Login (email) authentication on the login page.
33. `AUTH_METHOD_SMS_REGISTER_ENABLED`: Enable or disable Login (SMS) authentication method.
34. `AUTH_METHOD_SMS_REGISTER_LABEL`: The label for Login (SMS) authentication button on the login page.
35. `AUTH_METHOD_SMS_REGISTER_DESCRIPTION`: The description for Login (SMS) authentication on the login page.

36. `SYNC_LDAP_ENABLED`: Enable or disable synchronization with LDAP.
37. `SYNC_LDAP_SERVER`: The LDAP server's URL.
38. `SYNC_LDAP_BIND_USER_DN`: The Distinguished Name (DN) used to bind to the LDAP server.
39. `SYNC_LDAP_BIND_USER_PASSWORD`: The password for the bind user on the LDAP server.
40. `SYNC_LDAP_SEARCH_BASE_DN`: The base DN used when searching the LDAP directory.
41. `SYNC_LDAP_SEARCH_FILTER`: The filter used when searching the LDAP directory.
    The placeholder `@ID` is replaced with
    the user's ID.

42. `PROFILES_ENCRYPTION_TYPE_IOS_ONLY`: Type of encryption defined for the creation of the profiles, for iOS only.
43. `CAPPORT_ENABLED`: Enable or disable Capport DHCP configuration.
44. `CAPPORT_PORTAL_URL`: Domain that is from the entity hosting the service.
45. `CAPPORT_VENUE_INFO_URL`: Domain where the user is redirected after clicking the DHCP notification.
46. `SMS_USERNAME`: Budget SMS Username.
47. `SMS_USER_ID`: Budget SMS User ID.
48. `SMS_HANDLE`: Budget SMS Handle hash.
49. `SMS_FROM`: Entity sending the SMS for the users.
50. `SMS_TIMER_RESEND`: Timer in minutes to make the user wait to resend a new SMS.

51. `TOS_LINK`: Terms and Conditions URL
52. `PRIVACY_POLICY_LINK`: Privacy and Policy URL

#### With these environment variables, you can configure and customize various aspects of the project, such as database connections, SAML settings, login methods, and more.

# How the Platform Works

## Main Page

### 1. Main Page Overview - Live Mode

![Main_Page_Demo_False](assets/wba_screenshots/main_page_live_mode.png)

The main page in platform mode (**Live**) provides a user-friendly interface for users with
individual accounts.
The page welcomes visitors with a customisable banner that includes the company logo, welcome text,
and a brief summary of the portal's features.

Depending on the settings made by the administrator, users can log in using multiple authentication methods such as
SAML, Google, and Register authentication.
The main page provides a smooth and customized experience, allowing users to
safely and quickly connect to Wi-Fi networks.

### 1.2 Main Page Overview - Demo Mode

![Main_Page_Demo_True](assets/wba_screenshots/main_page_demo_mode.png)

The main page in platform mode (**Demo**) displays the platform's possibilities for users who want to
browse the portal without creating individual accounts.
The demo mode displays only the option for "demo login" on the
page, providing a simplified appearance.
To show the portal's key functions, SAML and other login methods are disabled,
allowing users to explore the system's functionalities using a demo profile built based on the given email.

Users are informed via a demo warning that the system is now in demo mode.

### 2. Verification Step

![Verification_Step](assets/wba_screenshots/verification_step.png)

The platform begins the verification procedure to validate the user after registration or when in demo mode. The
verification system sends an email or sms to the user's provided email or phone number address.
This procedure contains a unique verification link
or code that the user must click to complete the verification process.

The verification phase is a critical to validate the user's identity and makes sure their email or phone number
address is valid on the portal.
The user validates ownership of the email account and shows permission to use the OpenRoaming
Provisioning Portal by clicking the verification link or by submitting the code given on the email. This procedure
improves the platform's security by preventing unwanted access.

### 3. After Verification

![After_Verification](assets/wba_screenshots/after_verification.png)

After successfully completing the verification process, the user will be directed to this page.
The actions taken during the verification process depend on the mode in
which the user created their account.

#### 3.1 Live Mode

If the user created an account in live mode (platform mode set to **Live**), they will receive an email with
essential account information. The email includes a link to return to the verified portal, a verification code, and a
randomly generated password. With this information, the user can log in securely to the OpenRoaming Provisioning Portal.

#### 3.2 Demo Mode

Users who registered an account in demo mode (platform mode set to **Demo**) can access the verified portal instantly.
Because
demo mode allows users to explore the site without registering individual accounts, the verification procedure makes
sure that the user's session is safe.
Based on the submitted email, the system creates a demo profile that grants access
to the portal's features while keeping user data secure.

### 4. Download Profile

At the final stage of the process, users have the option to download a profile customized for their specific operating
system.
The OpenRoaming Provisioning Portal has intelligent auto-detection that identifies the type of device being
used.

When the user clicks the "Download" button, the portal generates a profile with information based on the user's account
information.
This profile is designed to improve the Wi-Fi experience by providing smooth connectivity and increased
security.

The downloaded profile includes all the essential settings and variables to make connecting to Wi-Fi networks easier.
The OpenRoaming Provisioning Portal improves Wi-Fi connectivity by giving a user-specific profile, making it easier and
more convenient for consumers and companies.

With this final step, users can effortlessly connect to Wi-Fi networks in various contexts, whether it's in public
hotspots, corporate networks, or events and conferences, experiencing the full benefits of the OpenRoaming Provisioning
Portal.
Say goodbye to manual login problems and enjoy a seamless, secure, and personalized Wi-Fi experience! 📶🔒🌐

## Admin Dashboard Overview

The OpenRoaming Provisioning Portal also has an easy and intuitive admin interface, allowing administrators to easily
administer and customize the platform.
The admin dashboard includes a number of features and tools that help simplify
the Wi-Fi provisioning process and guarantee that it runs smoothly. Here's an overview of the admin dashboard and its
primary features:

### 1. Admin Login

![Admin_Login](assets/wba_screenshots/admin_login.png)

The admin dashboard is only accessible after a secure login.
To access the dashboard, administrators must provide their
credentials **(admin@example.com/gnimaornepo)**. The login page provides authorized users with a secure and private
entry point to control the platform.

### 2. Admin Page

![Admin_Page](assets/wba_screenshots/admin_page.png)

Administrators are presented with the dashboard overview after successfully logging in.
Administrators may quickly
access/search information about the users on the portal, the system customization, the current applied settings and
other essential data.
And if needed, they can check all the statistics of the portal.
Statistics like, profiles downloaded in which device, what method of authentication the users used, etc...
