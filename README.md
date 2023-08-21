# 🌐︎ OpenRoaming Provisioning Portal

Welcome to the OpenRoaming Provisioning Portal - Your One-Stop Solution for Automated Device Authentication on Wireless Networks! 🚀
The OpenRoaming Provisioning Portal improves the process of connecting to Wi-Fi in any area by creating a secure and unique profile for each user. With automatic device authentication, you can enjoy an easy and secure Wi-Fi experience.

## Why it was created?
This Portal was created with the objective of **simplifying Wi-Fi connectivity** and improving security for users in a variety of contexts. We think that **everyone should have access to secure Wi-Fi** without having to log in several times. Our goal is to make Wi-Fi connectivity for people and companies easier, quicker, and more user-friendly.

## How it works?
OpenRoaming is an innovative technology that is changing the way people connect to Wi-Fi networks. It is an open standard that has been developed to **enable globally, secure, and automatic Wi-Fi connectivity**.

Users are able to connect to Wi-Fi networks using OpenRoaming without asking for login credentials. Instead, makes use of **digital certificates and secure authentication mechanisms**. This means that users can **switch quickly between Wi-Fi networks, such as public hotspots, corporate networks, and other places, without delays or several logins**.

The technology ensures that each user is **provided with a unique and secure profile** that caters to their specific needs and preferences.

The OpenRoaming Provisioning Portal acts as a **key enabler for OpenRoaming technology by simplifying the setup and configuration process** for network administrators.

You are taking an **important step** toward **improving Wi-Fi connection** for your users by providing a safe and **user-friendly experience for everybody** by utilizing the OpenRoaming Provisioning Portal.

### Benefits
- **Seamless Wi-Fi Connectivity**: Say goodbye to manual login problems and connect to Wi-Fi networks with simplicity. 📶
- **Improved Security**: Have peace of mind knowing that your personal information is safe with secure profile encryption. 🔒
- **Personalization**: Each user gets a unique profile tailored to their specific needs and preferences. 🎯
- **Scalability**: Because the portal is built for a high number of users and devices, it is excellent for both local and large-scale installations. 🌟

### Use Cases
- **Public Wi-Fi Hotspots**: Simplify the login method for users accessing Wi-Fi in coffee shops, airports, hotels, and other public locations. ☕️✈
- **Corporate Networks**: Facilitate employee onboarding and Wi-Fi access in workplaces while maintaining secure connectivity for all devices. 💼
- **Events and Conferences**: Provide seamless Wi-Fi access to attendees with personalized profiles, enhancing the overall event experience. 🎉

Whether you're our solution aims to exceed your expectations and make  Wi-Fi connectivity better. Let's embark on this journey together and redefine how we connect wirelessly! 🚀

## 🛠️ Tools Used 🛠️
These are some of the most important tools used on the development of this project.
- **PHP**: Open source general-purpose scripting language that is especially suited for web development.
- **Symfony Framework**: The core of the portal, the Symfony framework provides a solid and scalable base for web applications.
- **Twig Templating Engine**: Generates consistent, responsive views by separating logic.
- **MySQL Database**: Efficiently method to save and return user profiles and settings of the portal.
- **Docker**: Encapsulating the project in containers to improve deployment and compatibility.

## Getting Started - Setup Guide
In this guide, we'll lead you through the setup of the OpenRoaming Provisioning Portal step by step.

To understand how it works, you'll get basic knowledge behind each stage. You will possess a fully working automatic device authentication for your wireless networks by the end of this guide. Let's get started! 🚀

### Prerequisites:
- Docker (required for running the application)
- Docker-compose (required for managing multiple containers)
- Node Js - 16 or higher (required for building front-end assets)
- Git (optional, if you prefer to clone the repository)


### How to get the Project
You have two options to get the project:
1. **Download Release Package**: Download the release package from the releases section on GitHub. This package contains only the required components to run the OpenRoaming Provisioning Portal, including `.env.sample`, `docker-compose.yml`, and other necessary files.


2. **Clone the Repository**: If you're familiar with Git and want to access the complete source code, you can clone the repository using the following command:

```bash
- git clone <repository-url>
```

### ⚙️ Installation
Please follow the instructions below, on the root folder of the project, to prepare it:

1. **Update Environment Variables**: After you have obtained the project, make sure to update your environment variables. A sample file named `.env.sample` is provided in the project root directory. Duplicate the sample file and rename it to `.env`. You can then modify the environment variables to match your specific configuration. 🗝️

**Note**: When updating the database credentials in the `.env` file, make sure they **match the credentials specified in the docker-compose.yml** file. Failure to match the credentials may result in the application being unable to connect to the database.

2. **Build and Start Services**: Use Docker to build and start the necessary services. Execute the following command: 🐳

```bash
- docker-compose up -d
```
3. **Check Container Status**: After executing the previous command, ensure that all containers for each service are appropriately formed. The following command may be used to verify the status of each container, example:

```bash
- docker ps
```

⇓ After you create the containers they should look like this. ⇓

```bash
Starting cc-openroaming-provisioning-web_mailcatcher_1 ... done
Starting cc-openroaming-provisioning-web_web_1         ... done
Starting cc-openroaming-provisioning-web_memcached_1   ... done
Starting cc-openroaming-provisioning-web_mysql_1       ... done
```
4. **Upload Certificates**: Upload your certificate files to the `signing-keys` directory for the portal generates profiles based on your certificates.

5. **Generate PFX Signing Key**: Now, inside the `web` container, go to the tools directory and run the generatePfx script by doing this:

```bash
- docker exec -it <web-container-id> bash
- cd tools
- sh generatePfxSigningKey.sh
```

6. **Migrations, Fixtures and Permissions**: Still inside of the`web` container, you need to run this 3 commands to load the database schema, load is respective settings and add permissions to a specific folder to save images:

```bash
- php bin/console doctrine:migrations:migrate
- php bin/console doctrine:fixtures:load
- chown -R www-data:www-data /var/www/openroaming/public/resources/uploaded/
```

**IMPORTANT**: After you load the fixtures by running the second command, you need to change the following environment variable:

`RADIUS_TRUSTED_ROOT_CA_SHA1_HASH`: The SHA1 hash of your RADIUS server's trusted root CA. The default value is set to the SHA1 hash of the LetsEncrypt CA.

This number is needed to validate the RADIUS server's certificate. If you use a different CA for your RADIUS server, you must replace this value with the SHA1 hash of your CA's root certificate. **Connections errors** can happen if the right SHA1 hash is not provided.

Make sure to check the `SettingFixture.php` file for any reference about the default data and check the migrations about the database on the migrations folder of the project.

### 🛑 Important Security Notice after Installation 🛑

**It is critical to change the application to "prod"** mode before exposing the OpenRoaming Provisioning Portal to the internet or any production environment. Running the portal in "dev" mode on a public network **could reveal vital information and debug logs to possible attackers**, providing serious risks for security.

## Congratulations! 🎉
You've successfully completed the installation process of the OpenRoaming Provisioning Portal. 🚀

Now, it's time to access your fully set up portal! 🌐

To get started, open your favorite web browser and type the following address in the URL bar:
http://YOUR_SERVER_IP:80

Replace YOUR_SERVER_IP with your server's real IP address or domain name. If you are running the portal locally, you can use localhost for an IP address.

If you encounter any issues or have any questions along the way, don't hesitate to check to the [**Troubleshooting**](#troubleshooting) section on this README or reach out to our support team for assistance.


Thank you for choosing the OpenRoaming Provisioning Portal. We hope it helps your Wi-Fi experience and makes it easier to connect in any location! 💻📱


## 🚧 Troubleshooting
Here are some probable troubleshooting issues you may experience during the OpenRoaming Provisioning Portal installation:

1. **Missing or Incorrect Environment Variables**: Check if you don't forget to update the environment variables in the `.env` file. Make sure you have carefully followed the instructions to duplicate the `.env.sample` file and update the necessary variables with the correct values.
2. **Docker Compose Errors**: Docker Compose may encounter problems if your system setup or Docker version does not meet the prerequisites. Check if you have the latest Docker and Docker Compose versions installed.
3. **Container Not Running**: If you encounter errors while checking container status with `docker ps` command, it could indicate that the containers did not start correctly. Make sure you have followed the installation steps correctly and have the necessary permissions to run Docker containers. Don't forget to check if you don't have any container using the same ports necessary to run this project.
4. **DevMode instead of Production**: It's essential to switch the OpenRoaming Provisioning Portal to Production Mode (prod) when deploying it on the internet. Running the portal in Development Mode (dev) can lead to security vulnerabilities and suboptimal performance. Again please gou check your `.env` file and change it to prod.
5. **Generate Pfx-Signing-Key**: If you get a **Permission denied** error while trying to run the script, you have to grant executable permissions to the script file before executing it.

To solve this, use the chmod command inside the `web` container, to give the script executable rights.
```bash
- docker exec -it <web-container-id> bash
- chmod +x tools/generatePfxSigningKey.sh
```

## 📞 Contact and Support
If you have any problems installing or using the OpenRoaming Provisioning Portal, please feel free to contact us via email our to open an issue on this repository:
- **Email**: creative@tetrapi.pt

Please don't hesitate to reach out to us for any assistance you may need.

# How it Looks and How it Works?
Now we will show how the project looks, and give you some base information about how it works.

## Production Mode && Demo Mode

The project provides two modes: demo mode set to **TRUE** or **FALSE**, each serving to different needs.

- **Demo Mode (TRUE)**: When demo mode is set to TRUE, the system generates demo profiles based on the submitted email. This allows users to explore and test the portal's functionality without the need to create a user account. In demo mode, only "demo login" is displayed, and SAML and other login methods are disabled, regardless of other settings. A demo warning is also displayed, indicating that the system is in demo mode.

When this mode is activated **it's required** to verify the account every time the user want to download a profile again, because it's a new demo account being generated on the portal.
- **Production Mode (FALSE)**: On the other hand, when demo mode is set to FALSE, profiles are generated based on individual user accounts inside the project. This offers a completely customized and secure Wi-Fi experience adapted to the interests and needs of each user. Users can set up accounts in production mode and use all available login methods, including SAML and Google authentication.

When this mode is activated **it's not required** to verify the user account several time.

## 🔧 Environment Variables

The OpenRoaming Provisioning Portal utilizes environment variables for its configuration. Below is an overview of the different variables and their functions:

- `APP_ENV`: This sets the environment mode for the Symfony application. It can be `dev` or `prod`.
- `APP_SECRET`: This is the application secret used by Symfony for encrypting cookies and generating CSRF tokens.
- `DATABASE_URL`: This is the connection string for the primary MySQL database. It should be in the format `mysql://user:pass@host:port/dbname`.
- `DATABASE_FREERADIUS_URL`: This is the connection string for the FreeRADIUS MySQL database, used for RADIUS related operations. It should be in the format `mysql://user:pass@host:port/dbname`.
- `MESSENGER_TRANSPORT_DSN`: This defines the transport (e.g., AMQP, Doctrine, etc.) that Symfony Messenger will use for dispatching messages. The value `doctrine://default?auto_setup=0` uses Doctrine DBAL with auto setup disabled.
- `MAILER_DSN`: This sets the transport for sending emails via the Symfony Mailer component. The value `null://null` disables sending emails. The default value for this setting is : `native://default`

### 🔒 SAML Specific Settings

These variables are needed to set up the SAML Service Provider (SP) and Identity Provider (IdP):

- `SAML_IDP_ENTITY_ID`: This is the entity ID (URI) of the IdP.
- `SAML_IDP_SSO_URL`: This is the URL of the IdP's Single Sign-On (SSO) service.
- `SAML_IDP_X509_CERT`: This is the X509 certificate from the IdP, used for verifying SAML responses.
- `SAML_SP_ENTITY_ID`: This is the entity ID (URI) of the SP.
- `SAML_SP_ACS_URL`: This is the URL of the SP's Assertion Consumer Service (ACS), which processes SAML assertions from the IdP.

### 🛠️ Settings Table
The OpenRoaming Provisioning Portal has a detailed "setting" table that allows you to customize the application to your individual needs. Here's a rundown of several important variables and their functions:

1. `RADIUS_REALM_NAME`: The realm name for your RADIUS server.
2. `DISPLAY_NAME`: The name used on the profiles.
3. `PAYLOAD_IDENTIFIER`: The identifier for the payload used on the profiles.
4. `OPERATOR_NAME`: The operator name  used on the profiles.
5. `DOMAIN_NAME`: The domain name used for the service.
6. `RADIUS_TLS_NAME`: The hostname of your RADIUS server used for TLS.
7. `NAI_REALM`: The realm used for Network Access Identifier (NAI).
8. `RADIUS_TRUSTED_ROOT_CA_SHA1_HASH`: The SHA1 hash of your RADIUS server's trusted root CA (Defaults to LetsEncrypt CA).

**IMPORTANT**: The LetsEncrypt CA's SHA1 hash is set as the default value. This number is important since it is needed to validate the RADIUS server's certificate.

If you use a different CA for your RADIUS server, you must replace this value with the SHA1 hash of your CA's root certificate. **Connection errors** can happen if the right SHA1 hash is not provided.

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

#### With these environment variables, you can configure and customize various aspects of the project, such as database connections, SAML settings, login methods, and more.

# How the Platform Works

## Main Page

### 1. Main Page Overview - Demo Mode - False
![Main_Page_Demo_False](assets/wba_screenshots/main_page_demo_false.png)

The main page in production mode (demo mode set to **FALSE**) provides a user-friendly interface for users with individual accounts. The page welcomes visitors with a customisable banner that includes the company logo, welcome text, and a brief summary of the portal's features.

Depending on the settings made by the administrator, users can log in using multiple authentication methods such as SAML, Google, and Register authentication. The main page provides a smooth and customized experience, allowing users to safely and quickly connect to Wi-Fi networks.

### 1.2 Main Page Overview - Demo Mode - True
![Main_Page_Demo_True](assets/wba_screenshots/main_page_demo_true.png)

The main page in demo mode (demo mode set to **TRUE**) displays the platform's possibilities for users who want to browse the portal without creating individual accounts. The demo mode displays only the option for "demo login" on the page, providing a simplified appearance. To show the portal's key functions, SAML and other login methods are disabled, allowing users to explore the system's functionalities using a demo profile built based on the given email.

Users are informed via a demo warning that the system is now in demo mode.

### 2. Verification Step
![Verification_Step](assets/wba_screenshots/verification_step.png)

The platform begins the verification procedure to validate the user after registration or when in demo mode. The verification system sends an email to the user's provided email address. This email contains an unique verification link and code that the user must click in order to complete the verification procedure.

The verification phase is a critical security defend that validates the user's identity and makes sure their email address is valid. The user validates ownership of the email account and shows permission to use the OpenRoaming Provisioning Portal by clicking the verification link or by submiting the code given on the email. This procedure improves the platform's security by preventing unwanted access.

### 3. After Verification

![After_Verification](assets/wba_screenshots/after_verification.png)

After successfully completing the verification process by clicking the verification link or submitting the verification code, the user will be directed to this page. The actions taken during the verification process depend on the mode in which the user created their account.

#### 3.1 Production Mode

If the user created an account in production mode (demo mode set to **FALSE**), they will receive an email with essential account information. The email includes a link to return to the verified portal, a verification code, and a randomly generated password. With this information, the user can log in securely to the OpenRoaming Provisioning Portal.

#### 3.2 Demo Mode

Users who registered an account in demo mode (demo mode set to TRUE) can access the verified portal instantly. Because demo mode allows users to explore the site without registering individual accounts, the verification procedure makes sure that the user's session is safe. Based on the submitted email, the system creates a demo profile that grants access to the portal's features while keeping user data secure.

### 4. Download Profile

At the final stage of the process, users have the option to download a profile customized for their specific operating system. The OpenRoaming Provisioning Portal has intelligent auto-detection that identifies the type of device being used.

When the user clicks the "Download" button, the portal generates a profile with information based on the user's account information. This profile is designed to improve the Wi-Fi experience by providing smooth connectivity and increased security.

The downloaded profile includes all of the essential settings and variables to make connecting to Wi-Fi networks easier. The OpenRoaming Provisioning Portal improves Wi-Fi connectivity by giving a user-specific profile, making it easier and more convenient for consumers and companies.

With this final step, users can effortlessly connect to Wi-Fi networks in various contexts, whether it's in public hotspots, corporate networks, or events and conferences, experiencing the full benefits of the OpenRoaming Provisioning Portal. Say goodbye to manual login problems and enjoy a seamless, secure, and personalized Wi-Fi experience! 📶🔒🌐

## Admin Dashboard Overview

The OpenRoaming Provisioning Portal also has an easy and intuitive admin interface, allowing administrators to easily administer and customize the platform. The admin dashboard includes a number of features and tools that help simplify the Wi-Fi provisioning process and guarantee that it runs smoothly. Here's an overview of the admin dashboard and its primary features:

### 1. Admin Login

![Admin_Login](assets/wba_screenshots/admin_login.png)

The admin dashboard is only accessible after a secure login. To access the dashboard, administrators must provide their credentials **(admin@example.com/gnimaornepo)**. The login page provides authorized users with a secure and private entry point to control the platform.

### 2. Admin Page
![Admin_Page](assets/wba_screenshots/admin_page.png)

Administrators are presented with the dashboard overview after successfully logging in. Administrators may quickly access/search information about the users on the portal, the system customization, the current applied settings and another essential data.

### 3. Options
![Options](assets/wba_screenshots/options.png)

The "Options" button is a feature that offers users a simple dropdown menu with various important actions and capabilities. For authorized users, the button provides access to improving the experience on the OpenRoaming Provisioning Portal.
