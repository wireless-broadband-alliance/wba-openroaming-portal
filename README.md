
# OpenRoaming Provisioning Portal

The OpenRoaming Provisioning Portal is a tool that enables automated device authentication on wireless networks. Here's a simple guide to help you get started with it.

## Prerequisites
- Docker
- Docker-compose
- Git (if you plan to clone the repository)

## Getting Started
1. First clone the repository or download the zipped project package.

```bash
git clone <repository-url>
```

OR

Unzip the project package.

2. Authenticate with TETRAPI GitLab registry:

```bash
docker login registry.tetrapi.pt
```

Alternatively, you can build the image yourself using the provided Dockerfile.

3. Update your environment variables. You can find a sample in the `.env.sample` file provided in the project root directory. Make sure you duplicate the sample file and rename it to `.env`.

4. Run docker-compose to build and start the services:

```bash
docker-compose up -d
```

## Post Installation
Once the containers are up and running, you'll need to perform a few more steps.

1. Go into the `web` container:

```bash
docker exec -it <web-container-id> bash
```

2. Run migrations to set up your database schema:

```bash
php bin/console doctrine:migrations:migrate
```

3. Load fixtures to populate your database with initial data:

```bash
php bin/console doctrine:fixtures:load
```

4. Upload your certificate files to the `signing-keys` directory.

5. Inside the `web` container, navigate to the `tools` directory and run the `generatePfx` script:

```bash
cd tools
sh generatePfxSigningKey.sh
```

6. Finally, connect to your MySQL database instance and update the details on the `settings` table according to your requirements.

## Troubleshooting
If you encounter any issues during setup, please check the logs of the relevant Docker container. You can view the logs with the following command:

```bash
docker logs <container-id>
```

## Development Build

To set up a development build, follow the steps below. This build uses `docker-compose-dev.yml` for configuration and includes steps for building the necessary assets.

1. Run docker-compose to build and start the services using the development compose file:

```bash
docker-compose -f docker-compose-dev.yml up -d
```

2. Go into the `web` container:

```bash
docker exec -it <web-container-id> bash
```

3. Install composer dependencies:

```bash
composer i
```

4. Install npm dependencies:

```bash
npm i
```

5. Build assets:

```bash
npm run build
```

6. Run migrations to set up your database schema:

```bash
php bin/console doctrine:migrations:migrate
```

7. Load fixtures to populate your database with initial data:

```bash
php bin/console doctrine:fixtures:load
```

8. Upload your certificate files to the `signing-keys` directory.

9. Inside the `web` container, navigate to the `tools` directory and run the `generatePfx` script:

```bash
cd tools
sh generatePfxSigningKey.sh
```

10. Finally, connect to your MySQL database instance and update the details on the `settings` table according to your requirements.

## Environment Variables

This application uses environment variables for configuration. Here's an overview of the different variables and what they do:

- `APP_ENV`: This sets the environment mode for the Symfony application. It can be `dev` or `prod`.
- `APP_SECRET`: This is the application secret used by Symfony for encrypting cookies and generating CSRF tokens.
- `DATABASE_URL`: This is the connection string for the primary MySQL database. It should be in the format `mysql://user:pass@host:port/dbname`.
- `DATABASE_FREERADIUS_URL`: This is the connection string for the FreeRADIUS MySQL database, used for RADIUS related operations. It should be in the format `mysql://user:pass@host:port/dbname`.
- `MESSENGER_TRANSPORT_DSN`: This defines the transport (e.g., AMQP, Doctrine, etc.) that Symfony Messenger will use for dispatching messages. The value `doctrine://default?auto_setup=0` uses Doctrine DBAL with auto setup disabled.
- `MAILER_DSN`: This sets the transport for sending emails via the Symfony Mailer component. The value `null://null` disables sending emails.

### SAML Specific Settings

These environment variables are used to configure the SAML Service Provider (SP) and Identity Provider (IdP):

- `SAML_IDP_ENTITY_ID`: This is the entity ID (URI) of the IdP.
- `SAML_IDP_SSO_URL`: This is the URL of the IdP's Single Sign-On (SSO) service.
- `SAML_IDP_X509_CERT`: This is the X509 certificate from the IdP, used for verifying SAML responses.
- `SAML_SP_ENTITY_ID`: This is the entity ID (URI) of the SP.
- `SAML_SP_ACS_URL`: This is the URL of the SP's Assertion Consumer Service (ACS), which processes SAML assertions from the IdP.

## Settings Table

This table in the MySQL database contains various configuration options for the OpenRoaming Provisioning Portal. Here's a brief description of each row and its use:

1. `RADIUS_REALM_NAME`: The realm name for your RADIUS server.
2. `DISPLAY_NAME`: The name used on the profiles.
3. `PAYLOAD_IDENTIFIER`: The identifier for the payload used on the profiles.
4. `OPERATOR_NAME`: The operator name  used on the profiles.
5. `DOMAIN_NAME`: The domain name used for the service.
6. `RADIUS_TLS_NAME`: The hostname of your RADIUS server used for TLS.
7. `NAI_REALM`: The realm used for Network Access Identifier (NAI).
8. `RADIUS_TRUSTED_ROOT_CA_SHA1_HASH`: The SHA1 hash of your RADIUS server's trusted root CA (Defaults to LetsEncrypt CA).
9. `DEMO_MODE`: Enable or disable demo mode. When enabled, only "demo login" is displayed, and SAML and other login methods are disabled regardless of other settings. A demo warning will also be displayed.
10. `PAGE_TITLE`: The title displayed on the webpage.
11. `CUSTOMER_LOGO`: The resource path or URL to the customer's logo image.
12. `OPENROAMING_LOGO`: The resource path or URL to the OpenRoaming logo image.
13. `WELCOME_TEXT`: The welcome text displayed on the user interface.
14. `WELCOME_DESCRIPTION`: The description text displayed under the welcome text.
15. `CONTACT_EMAIL`: The email address for contact inquiries.
16. `AUTH_METHOD_SAML_ENABLED`: Enable or disable SAML authentication method.
17. `AUTH_METHOD_SAML_LABEL`: The label for SAML authentication on the login page.
18. `AUTH_METHOD_SAML_DESCRIPTION`: The description for SAML authentication on the login page.
19. `AUTH_METHOD_GOOGLE_LOGIN_ENABLED`: Enable or disable Google authentication method.
20. `AUTH_METHOD_GOOGLE_LOGIN_LABEL`: The label for Google authentication button on the login page.
21. `AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION`: The description for Google authentication on the login page.
22. `AUTH_METHOD_REGISTER_METHOD_ENABLED`: Enable or disable Register authentication method.
23. `AUTH_METHOD_REGISTER_METHOD_LABEL`: The label for Register authentication button on the login page.
24. `AUTH_METHOD_REGISTER_METHOD_DESCRIPTION`: The description for Register authentication on the login page.
25. `SYNC_LDAP_ENABLED`: Enable or disable synchronization with LDAP.
26. `SYNC_LDAP_SERVER`: The LDAP server's URL.
27. `SYNC_LDAP_BIND_USER_DN`: The Distinguished Name (DN) used to bind to the LDAP server.
28. `SYNC_LDAP_BIND_USER_PASSWORD`: The password for the bind user on the LDAP server.
29. `SYNC_LDAP_SEARCH_BASE_DN`: The base DN used when searching the LDAP directory.
30. `SYNC_LDAP_SEARCH_FILTER`: The filter used when searching the LDAP directory. The placeholder `@ID` is replaced with the user's ID.
31. `WALLPAPER_IMAGE`: The resource path or URL to the wallpaper image.
32. `DEMO_WHITE_LABEL`: Removes everything about the demo layout.
33. `VALID_DOMAINS_GOOGLE_LOGIN`: Defines the valid domains to authenticate with Google, when it's empty, he lets anyone with a google account login
