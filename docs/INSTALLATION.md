# Installation Guide

This guide is intended solely to assist in setting up the OpenRoaming Provisioning Portal. It provides step-by-step
instructions for configuring the Portal.

Please follow the instructions below, starting from the **root** folder of the project, to prepare it:

## 1. **Update Environment Variables**

After you have obtained the project, make sure to update your environment
variables. A sample file named `.env.sample` is provided in the project root directory. Duplicate the sample file and
rename it to `.env`. You can then modify the environment variables to match your specific configuration.

**Note**: When updating the database credentials in the `.env` file, make sure they **match the credentials specified in
the docker-compose.yml** file.
Failure to match the credentials will result in the application being unable to connect to
the database.

## 2. **Start Services**

Use Docker to start the necessary services. There are two scenarios:

#### a) **Production / prebuilt image (default)**

```bash
docker compose up -d
```

This will pull the prebuilt image with all dependencies included.

---

#### b) **Local Development / Testing**

If you want to use `docker-compose-local.yml` for **development or testing**, the container **does not include
preinstalled dependencies**. Follow these steps **before starting the containers**:

1. **Install project dependencies** on your host machine:

```bash
sudo composer install     # PHP dependencies
```

2. **Create the containers** on your host machine:

```bash
docker-compose -f docker-compose-local.yml up -d
```

## 3. **Check Containers Status**

After executing the previous command, ensure that all containers for each service are
appropriately formed. The following command may be used to verify the status of each container, example:

```bash
docker ps
```

## 4. **Build Tailwind Dependencies**

First, enter the `web` container:

```bash
docker exec -it <web-container-id> bash
```

Once inside the container, compile the required Tailwind CSS and frontend assets. The **default build command** is:

```bash
php bin/console tailwind:build
```

### For Production

To compile and **minify** assets for production:

```bash
php bin/console tailwind:build --minify
php bin/console asset-map:compile
```

### For Development

For continuous development and watching changes:

```bash
php bin/console tailwind:build -w
```

> For a complete guide on building and managing assets in different environments (development or production), please
> refer to the [Asset Mapper Documentation](ASSETMAPPER.md).

### 5. **Upload Certificates**

Still inside the `web` container, upload your certificate files to the portal.

1. **Copy your certificate files** to the `public/signing-keys` folder.

2. The portal supports **Let's Encrypt certificates** (commonly used for **OpenRoaming / FreeRADIUS authentication**).
   The certificate files should be placed using the following structure:

```
public/signing-keys/
 ├── cert.pem
 ├── chain.pem
 ├── fullchain.pem
 ├── privkey.pem
 └── ca/
     └── ca.pem
```

3. **In case you also have your custom CA**, please place it inside the `ca/` folder as `ca.pem`.

```text
public/signing-keys/ca/ca.pem
```

> **Note:** When using Let's Encrypt, these files are typically generated in
> `/etc/letsencrypt/live/<your-domain>/` and can be copied into the folder above.

> Make sure `ca.pem` is in the `ca/` subfolder — the portal uses this folder specifically for trusted root CAs.
> You can copy files from your host machine into the container or directly into the folder before creating profiles.

## 6. **Generate PFX Signing Key**

While still inside the `web` container, go to the `tools` directory and run the PFX generation script:

```bash
cd tools
sh generatePfxSigningKey.sh
```

## 7. **Migrations, Fixtures and Permissions**

Still inside of the`web` container, you need to run this 3 commands to load
the database schema, load is respective settings and add permissions to a specific folder to save images:

```bash
- php bin/console doctrine:migrations:migrate
- php bin/console doctrine:fixtures:load
- chown -R www-data:www-data /var/www/openroaming/public/resources/uploaded/
```

**IMPORTANT**:
After you load the fixtures by running the second command,
if you are not using Let’s Encrypt CA, you need to change the following
configuration:

`RADIUS_TRUSTED_ROOT_CA_SHA1_HASH`: The SHA1 hash of your RADIUS server's trusted root CA. The default value is set to
the SHA1 hash of the LetsEncrypt CA.
For that, you need to access the **Dashboard Page**, and in the radius configuration section change the setting.
Or just access the mysql container and update it there.

This hash is needed to validate the RADIUS server's certificate. If you use a different CA for your RADIUS server, you
must replace this value with the SHA1 hash of your CA's root certificate. **Connections errors** can happen if the right
SHA1 hash is not provided.

Make sure to check the `src/DataFixtures/SettingFixture.php` file for any reference about the default data and check the
migrations about
the database on the migrations folder of the project.

## 8. **Generate JWT Keys**

This step is required for the **API** configuration. To enable JWT authentication, you need to generate a key pair (
private and public keys). Make sure to run the
following command on the root folder of the project to generate these keys:

```bash
php bin/console lexik:jwt:generate-keypair 
```

or

```bash
php bin/console lexik:jwt:generate-keypair --passphrase=<your_passphrase>
```

This command will create the following files in the `config/jwt` directory:

- `private.pem` – the private key used to sign tokens.
- `public.pem` – the public key used to verify tokens.

Make sure to keep these keys secure, especially the private key.

## 9. **Configure JWT and CORS**

Make sure this configuration is set up on `.env` the JWT and CORS environment variables
in your `.env` file:

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE= # Update this to your actual passphrase

###> nelmio/cors-bundle ###
CORS_ALLOW_ORIGIN='^https?://(localhost|127.0.0.1)(:[0-9]+)?$'
###< nelmio/cors-bundle ###
```

Replace `openroaming` with the passphrase you used when generating the JWT keys from the last step.

The `CORS_ALLOW_ORIGIN` regex allows requests from `localhost` or `127.0.0.1` during local development.
Adjust it based on your deployment needs, and make sure to not use the default value from the sample in a production
environment.

## Important References Configurations

### For a complete installation of the portal, please follow these steps

These steps will enhance the portal's security and enable key features required for its full functionality
(Cron Commands, Microsoft login, Google Login, SAML login and GeoLite2).

### Set up a CRON Job for automation commands

For reference, all the previous documentation related to the **CRONGUI.md** was discontinued. Now the framework
handles all this automation, and the supervisor configuration takes cares of the process. If you want to run the
configuration command by yourself, you can run the following command:

```bash
php bin/console messenger:consume scheduler_default -vv
```

Also, all this setup is configured on the following file: `src/Schedule.php`

### Google Authenticator Credentials

For detailed steps on how to obtain your **Google Client ID** and **Google Client Secret**, please refer to
the [Google Client ID and Secret Guide](../docs/ProvidersGuides/GOOGLE_CLIENT.md).
Once obtained, you will use the following environment variables in your portal configuration:

- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`

### Microsoft Authenticator Credentials

For detailed instructions on how to get your **Microsoft Client ID** and **Microsoft Client Secret**, please refer to
the [Microsoft Client ID and Secret Guide](../docs/ProvidersGuides/MICROSOFT_CLIENT.md).

Once obtained, you will use the following environment variables in your portal configuration:

- `MICROSOFT_CLIENT_ID`
- `MICROSOFT_CLIENT_SECRET`

### SAML Authenticator Credentials

These variables are needed to set up the SAML Service Provider (SP) and Identity Provider (IdP):

For detailed instructions on how to get your **SAML IDP Credentials**, please refer to
the [SAML Service Provider (SP)](../docs/ProvidersGuides/SAML_IDP_CREDENTIALS.md).

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

### GeoLite Configuration

> **Important**: GeoLite2 is mandatory for a complete portal installation

GeoLite2 is important for personalizing the user experience based on their location. It allows the portal to identify
the user's region from their IP address, helping us adjust content, set cookies properly, and comply with local laws,
such as the GDPR. By using GeoLite2, we ensure that our portal delivers relevant content and respects privacy and
cookie consent regulations.

For detailed instructions on the GeoLite GUI setup, operations, and usage, refer to
the [GeoLite GUI Guide](../docs/GEOLITEGUI.md).

### Important Security Note after Installation

**It is critical to change the application to "prod"** mode before exposing the OpenRoaming Provisioning Portal to the
internet or any production environment. Running the portal in "dev" mode on a public network **could reveal vital
information and debug logs to possible attackers**, providing serious risks for security.

It's **recommended** to follow standard security practices, including:

- Properly configuring **firewalls** to **protect database servers** and another critical infrastructure.
- Ensuring all **software** and **dependencies** are **up to date** with the latest security patches.

And again, please **do not share** any of your generated keys from these installations steps and keep as safe as
possible.

## Congratulations!

You've successfully completed the installation process of the OpenRoaming Provisioning Portal.

Now, it's time to access your fully set-up portal!

To get started, open your favorite web browser and type the following address in the URL bar:
http://YOUR_SERVER_IP:80

Replace **YOUR_SERVER_IP** with your server's real IP address or domain name.
If you are running the portal locally, you can
use localhost for an IP address.
And make sure to use **port 80**, it's the default port of the project.

If you encounter any issues or have any questions along the way, don't hesitate to check to the **Troubleshooting**
section on this README or reach out to our support team for assistance.

Thank you for choosing the OpenRoaming Provisioning Portal. We hope it helps your Wi-Fi experience and makes it easier
to connect in any location!

## Troubleshooting

Here are some probable troubleshooting issues you may experience during the OpenRoaming Provisioning Portal
installation:

1. **Missing or Incorrect Environment Variables**: Check if you don't forget to update the environment variables in
   the `.env` file. Make sure you have carefully followed the instructions to duplicate the `.env.sample` file and
   update the necessary variables with the correct values.
2. **Docker Compose Errors**: Docker Compose may encounter problems if your system setup or Docker version does not meet
   the prerequisites. Check if you have the latest Docker and Docker Compose versions installed.
3. **Container Not Running**: If you encounter errors while checking container status with `docker ps` command, it could
   indicate that the containers did not start correctly. Make sure you have followed the installation steps correctly
   and have the necessary permissions to run Docker containers. Don't forget to check if you don't have any container
   using the same ports necessary to run this project.
4. **DevMode instead of Production**: It's essential to switch the OpenRoaming Provisioning Portal to Production Mode (
   prod) when deploying it on the internet. Running the portal in Development Mode (dev) can lead to security
   vulnerabilities and suboptimal performance. Again please gou check your `.env` file and change it to prod.
5. **Generate Pfx-Signing-Key**: If you get a **Permission denied** error while trying to run the script, you have to
   grant executable permissions to the script file before executing it.

To solve this, use the chmod command inside the `web` container, to give the script executable rights.

```bash
- docker exec -it <web-container-id> bash
- chmod +x tools/generatePfxSigningKey.sh
```

6. **VichUploader Cache Directory Not Writable (HTTP 500 Error)**:
   You may encounter an Internal Server Error 500 caused by the `vich_uploader` cache directory not being writable (
   `var/cache/prod/vich_uploader`). This typically happens after deployments or cache regeneration when folder
   permissions are incorrect.

   To fix this, ensure correct ownership and permissions:

   ```bash
   chown -R www-data:www-data /var/www/openroaming/var/cache/prod/vich_uploader
   chmod -R 777 /var/www/openroaming/var/cache/prod/vich_uploader
   ```

- This step should be executed **after deployment or when the cache directory is recreated**
- Its recommended to run during maintenance or controlled downtime if users are affected

## Important Reference

> After completing installation, make sure to review the [Security Notes](SECURITY.md) for important production
> considerations.

## Contact and Feedback

Your suggestions and questions will help us improve the platform's usability and experience.

For more information please contact: openroaming@wballiance.com
