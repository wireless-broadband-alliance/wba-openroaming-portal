# 🌐︎ OpenRoaming Provisioning Portal

Welcome to the OpenRoaming Provisioning Portal - Your One-Stop Solution for Automated Device Authentication on Wireless Networks! 🚀
The OpenRoaming Provisioning Portal improves the process of connecting to Wi-Fi in any area by creating a secure and unique profile for each user. With automatic device authentication, you can enjoy an easy and secure Wi-Fi experience.

This Portal was created with the objective of simplifying Wi-Fi connectivity and improving security for users in a variety of contexts. We think that everyone should have access to secure Wi-Fi without having to log in several times. Our goal is to make Wi-Fi connectivity for people and companies easier, quicker, and more user-friendly.

### Benefits
- Seamless Wi-Fi connectivity: Say goodbye to manual login problems and connect to Wi-Fi networks with simplicity. 📶
- Improved security: Have peace of mind knowing that your personal information is safe with secure profile encryption. 🔒
- Personalization: Each user gets a unique profile tailored to their specific needs and preferences. 🎯
- Scalability: Because the portal is built for a high number of users and devices, it is excellent for both local and large-scale installations. 🌟

### Use Cases
- Public Wi-Fi hotspots: Simplify the login method for users accessing Wi-Fi in coffee shops, airports, hotels, and other public locations. ☕️✈
- Corporate networks: Facilitate employee onboarding and Wi-Fi access in workplaces while maintaining secure connectivity for all devices. 💼
- Events and conferences: Provide seamless Wi-Fi access to attendees with personalized profiles, enhancing the overall event experience. 🎉

In this guide, we'll lead you through the setup of the OpenRoaming Provisioning Portal step by step. To understand how it works, you'll get basic knowledge behind each stage. You will possess a fully working automatic device authentication for your wireless networks by the end of this guide. Let's get started! 🚀
Let's get started

### Prerequisites
- Docker
- Docker-compose
- Node Js - 16 or higher
- Composer
- Git (if you prefer to clone the repository)

## How to get the Project
You have two options to get the project:
1. **Download Zip File**: You can download the project as a zip file and extract it to a directory on your machine.

2. **Clone the Repository**: If you're familiar with Git, you can clone the repository using the following command:

```bash
- git clone <repository-url>
```
## Installation Commands
Please follow the instructions below, on the root folder of the project, to prepare and install it:

1. **Update Environment Variables**: After you have obtained the project, make sure to update your environment variables. A sample file named `.env.sample` is provided in the project root directory. Duplicate the sample file and rename it to `.env`. You can then modify the environment variables to match your specific configuration. 🗝️
2. **Install Dependencies**: Before starting the project, you need to install its dependencies. Ensure that you have Node.js installed on your machine. Run the following command in your terminal to install the required packages:
```bash
- yarn build
```
3. **Build and Start Services**: Use Docker to build and start the necessary services. Execute the following command: 🐳

```bash
- docker-compose -f docker-compose.yml up -d
```
4. **Check Container Status**: After executing the previous command, ensure that all containers for each service are appropriately formed. The following command may be used to verify the status of each container, example:

```bash
- docker ps
```

⇓ Finally after you create the containers, they should look like this. ⇓

```bash
Starting cc-openroaming-provisioning-web_mailcatcher_1 ... done
Starting cc-openroaming-provisioning-web_web_1         ... done
Starting cc-openroaming-provisioning-web_memcached_1   ... done
Starting cc-openroaming-provisioning-web_mysql_1       ... done
```

## Post Installation
Congratulations on finishing the essential requirements 🎉! Now we need to get the project up and running.

1. **Access the web Container**: To make adjustments to the project, you'll need to access the `web` container. Type the following command in your terminal:

```bash
- docker exec -it <web-container-id> bash
```
2. **Composer Install**: Once inside the `web` container, use the composer install command to install all the required PHP dependencies for the project. Composer will read the composer.json file and download the necessary packages into the vendor directory.
```bash
- composer install
```
3. **NPM Run Build**: Use Node Package Manager (NPM) to build the frontend assets when running npm run build command. This instruction tells Webpack to bundle and generate the JavaScript, CSS, and other needed assets. The created files will be saved in the build directory.
```bash
- npm run build
```
4. **Set Up Database Schema**: After that, run the migration command to set up your database schema:

```bash
- php bin/console doctrine:migrations:migrate
```

5. **Load Initial Data**: Now we'll populate the database with the requested configuration data. This data it's located in "src/DataFixtures/SettingFixture.php". Execute it use the following command:

```bash
- php bin/console doctrine:fixtures:load
```


## Congratulations! 🎉
You've successfully completed the installation process of the OpenRoaming Provisioning Portal. 🚀

Now, it's time to access your fully set up portal! 🌐

To get started, open your favorite web browser and type the following address in the URL bar:
http://127.0.0.1:80

If you encounter any issues or have any questions along the way, don't hesitate to check to the [**Troubleshooting**](#troubleshooting) section on this README or reach out to our support team for assistance.


Thank you for choosing the OpenRoaming Provisioning Portal. We hope it helps your Wi-Fi experience and makes it easier to connect in any location! 💻📱


## 🔧 Environment Variables

This application uses environment variables for configuration. Here's an overview of the different variables and what they do:

- `APP_ENV`: This sets the environment mode for the Symfony application. It can be `dev` or `prod`.
- `APP_SECRET`: This is the application secret used by Symfony for encrypting cookies and generating CSRF tokens.
- `DATABASE_URL`: This is the connection string for the primary MySQL database. It should be in the format `mysql://user:pass@host:port/dbname`.
- `DATABASE_FREERADIUS_URL`: This is the connection string for the FreeRADIUS MySQL database, used for RADIUS related operations. It should be in the format `mysql://user:pass@host:port/dbname`.
- `MESSENGER_TRANSPORT_DSN`: This defines the transport (e.g., AMQP, Doctrine, etc.) that Symfony Messenger will use for dispatching messages. The value `doctrine://default?auto_setup=0` uses Doctrine DBAL with auto setup disabled.
- `MAILER_DSN`: This sets the transport for sending emails via the Symfony Mailer component. The value `null://null` disables sending emails.

### 🔒 SAML Specific Settings

These variables are needed to set up the SAML Service Provider (SP) and Identity Provider (IdP):

- `SAML_IDP_ENTITY_ID`: This is the entity ID (URI) of the IdP.
- `SAML_IDP_SSO_URL`: This is the URL of the IdP's Single Sign-On (SSO) service.
- `SAML_IDP_X509_CERT`: This is the X509 certificate from the IdP, used for verifying SAML responses.
- `SAML_SP_ENTITY_ID`: This is the entity ID (URI) of the SP.
- `SAML_SP_ACS_URL`: This is the URL of the SP's Assertion Consumer Service (ACS), which processes SAML assertions from the IdP.

### 🛠️ Settings Table
This application uses environment variables for configuration. Below is an overview of the different variables and their functions:

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

## 🚧 Troubleshooting
Here are some probable troubleshooting issues you may experience during the OpenRoaming Provisioning Portal installation:

1. **Missing or Incorrect Environment Variables**: Check if you don't forget to update the environment variables in the `.env` file. Make sure you have carefully followed the instructions to duplicate the `.env.sample` file and update the necessary variables with the correct values.
2. **Node.js Version Compatibility**: You can face problems during the yarn build step if you have an older version of Node.js installed on your machine. Make sure you have the correct version of Node.js installed. Version 16 or higher is required.
3. **Docker Compose Errors**: Docker Compose may encounter problems if your system setup or Docker version does not meet the prerequisites. Check if you have the latest Docker and Docker Compose versions installed.
4. **Container Not Running**: If you encounter errors while checking container status with `docker ps` command, it could indicate that the containers did not start correctly. Make sure you have followed the installation steps correctly and have the necessary permissions to run Docker containers. Don't forget to check if you don't have any container using the same ports necessary to run this project.
5. **Database Connectivity**: Database connectivity issues could happen you provide the incorrect database credentials or set up the database URL incorrectly. Check if you have the right database connection data in your `.env` file.
6. **Missing Node.js Packages**: During the npm run build step, you might encounter errors if you have not installed all the required Node.js packages. Ensure that you have run `yarn install` to install the required packages before executing `npm run build` on the `web` container.
7. **Composer Dependency Issues**: If you face issues during the `composer install` step that means Composer found problems while installing PHP dependencies. Check you have the necessary PHP version and extensions installed.
8. **Database Migration Errors**: If you have problems with database migrations, it may be due to database schema conflicts or other migration-related issues. To verify any related problems with migrations, go to the terminal and use the following commands to check the respective logs of the `web` container.
```bash
- docker ps
- docker logs <container-web-id>
```
