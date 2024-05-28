# ⚙️ Installation Guide
This guide has the only purpose of helping to set up the FreeOpenRoaming Provisioning Portal.

Please follow the instructions below, on the **root** folder of the project, to prepare it:

1. **Update Environment Variables**: After you have obtained the project, make sure to update your environment
   variables. A sample file named `.env.sample` is provided in the project root directory. Duplicate the sample file and
   rename it to `.env`. You can then modify the environment variables to match your specific configuration. 🗝️

**Note**: When updating the database credentials in the `.env` file, make sure they **match the credentials specified in
the docker-compose.yml** file.
Failure to match the credentials will result in the application being unable to connect to
the database.

2. **Build and Start Services**: Use Docker to build and start the necessary services. Execute the following command: 🐳

```bash
- docker-compose up -d
```
or, only for local usage and testing,
```bash
- docker-compose -f docker-compose-local.yml up -d
```

3. **Check Containers Status**: After executing the previous command, ensure that all containers for each service are
   appropriately formed. The following command may be used to verify the status of each container, example:

```bash
- docker ps
```

4. **Upload Certificates**:
   Upload your certificate files to the `public/signing-keys` directory for the portal o eventually generate profiles based on your certificates.
   You can either upload the certs to this folder, inside/outside the container web, but off course before creating it.

5. **Generate PFX Signing Key**: Now, inside the `web` container, go to the tools directory and run the generatePfx
   script by doing this:

```bash
- docker exec -it <web-container-id> bash
- cd tools
- sh generatePfxSigningKey.sh
```

6. **Migrations, Fixtures and Permissions**: Still inside of the`web` container, you need to run this 3 commands to load
   the database schema, load is respective settings and add permissions to a specific folder to save images:

```bash
- php bin/console doctrine:migrations:migrate
- php bin/console doctrine:fixtures:load
- chown -R www-data:www-data /var/www/openroaming/public/resources/uploaded/
```

**IMPORTANT**:
After you load the fixtures by running the second command,
if you are not using Let’s Encrypt CA, you need to change the following environment
variable:

`RADIUS_TRUSTED_ROOT_CA_SHA1_HASH`: The SHA1 hash of your RADIUS server's trusted root CA. The default value is set to
the SHA1 hash of the LetsEncrypt CA.
For that, you need to access the **Back Office Page**, and in the radius configuration section change the setting.
Or just access the mysql container and update it there.

This number is needed to validate the RADIUS server's certificate. If you use a different CA for your RADIUS server, you
must replace this value with the SHA1 hash of your CA's root certificate. **Connections errors** can happen if the right
SHA1 hash is not provided.

Make sure to check the `src/DataFixtures/SettingFixture.php` file for any reference about the default data and check the
migrations about
the database on the migrations folder of the project.

### 🛑 Important Security Note after Installation 🛑

**It is critical to change the application to "prod"** mode before exposing the OpenRoaming Provisioning Portal to the
internet or any production environment. Running the portal in "dev" mode on a public network **could reveal vital
information and debug logs to possible attackers**, providing serious risks for security.

And it's **recommended** to follow standard security practices, including:

- Properly configuring **firewalls** to **protect database servers** and another critical infrastructure.
- Ensuring all **software** and **dependencies** are **up to date** with the latest security patches.

## 🎉 Congratulations! 🎉

You've successfully completed the installation process of the OpenRoaming Provisioning Portal. 🚀

Now, it's time to access your fully set-up portal! 🌐

To get started, open your favorite web browser and type the following address in the URL bar:
http://YOUR_SERVER_IP:80

Replace **YOUR_SERVER_IP** with your server's real IP address or domain name.
If you are running the portal locally, you can
use localhost for an IP address.
And make sure to use **port 80**, it's the default port of the project.

If you encounter any issues or have any questions along the way, don't hesitate to check to the [**Troubleshooting
**](#troubleshooting) section on this README or reach out to our support team for assistance.

Thank you for choosing the OpenRoaming Provisioning Portal. We hope it helps your Wi-Fi experience and makes it easier
to connect in any location! 💻📱

## 🚧 Troubleshooting

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

## 📞 Contact and Support

We're here to help if you have any problems installing or using the OpenRoaming Provisioning Portal. You request
assistance by creating an issue in this repository.

Your suggestions and questions will help us improve the platform's usability and experience.
