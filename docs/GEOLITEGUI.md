# Steps to Download the GeoLite2-City.mmdb File from MaxMind

The GeoLite2-City database is distributed by MaxMind, and you need to follow these steps to download it successfully:

---

## 1. Register an Account on MaxMind

1. Navigate to the [MaxMind Sign Up page](https://www.maxmind.com/en/geolite2/signup).
2. Fill in the required details to create your MaxMind account.

---

## 2. Log in to Your MaxMind Account

1. After completing the registration and email verification, go to
   the [MaxMind Login page](https://www.maxmind.com/en/account/login).
2. Enter your registered email and password to access your account.

---

## 3. Download the GeoLite2-City.mmdb File

1. Once logged in, navigate to the **Manage License Keys** page in your MaxMind account.
2. Click on **Generate new license key**.
3. Follow the instructions on the page to generate a license key associated with your account.
4. Use the following command in your terminal to download the GeoLite2-City database file:
   ```bash
   wget "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=YOUR_LICENSE_KEY_HERE&suffix=tar.gz" -O GeoLite2-City.tar.gz
   ```
    - Replace `YOUR_LICENSE_KEY_HERE` with the license key generated in step 3.
    - The `-O GeoLite2-City.tar.gz` option specifies the name of the downloaded file.
5. Save the downloaded `.tar.gz` file to your preferred location.

---

## 4. Extract the MMDB File

1. After downloading, extract the `.tar.gz` file:
    - On Linux/macOS:
      ```bash
      tar -xvzf GeoLite2-City.tar.gz
      ```
    - On Windows, you can use tools like 7-Zip to extract the archive.
2. Inside the extracted files, locate the `GeoLite2-City.mmdb` file.

---

## 5. Use the MMDB File

- Place the `GeoLite2-City.mmdb` file in the designated directory, specifically at **docs/geoLiteDB/GeoLite2-City.mmdb
  **.
- Ensure portal is configured to correctly reference this file path for accessing the database.
- Verify the file path is accessible and readable by your portal to prevent potential runtime issues.

---

## 7. Set Correct File Permissions

After placing the `GeoLite2-City.mmdb` file in the specified directory, ensure the correct file permissions are applied
to avoid access issues. Use the following permissions as a reference:

```bash
root@your-system:/var/www/openroaming/docs/geoLiteDB# ls -la
total 57464
drwxrwxr-x 2 root root     4096 Feb 18 11:48 .
drwxr-xr-x 4 root root     4096 Feb 18 12:08 ..
-rw-rw-r-- 1 root root        0 Feb 18 11:35 .gitkeep
-rw-rw-r-- 1 root root 58827769 Feb 14 13:10 GeoLite2-City.mmdb
```

### Key Points for File Permissions:

- The directory (**geoLiteDB**) has permissions `drwxrwxr-x`, which allows read, write, and execute access for the owner
  and group, but only read and execute access for others.
- The `GeoLite2-City.mmdb` file has permissions `-rw-rw-r--`, which allows read and write access for the owner and
  group, and read-only access for others.

To apply these permissions, you can run:

```bash
chmod 775 /var/www/openroaming/docs/geoLiteDB
chmod 664 /var/www/openroaming/docs/geoLiteDB/GeoLite2-City.mmdb
```

Ensure the file and directory ownership matches your system's requirements, typically the user running the web server.

---

## Licensing and Usage of GeoLite2-City Database

The GeoLite2-City database is provided under the **GeoLite2 End User License Agreement (EULA)**. Here are the key
points:

1. **Redistribution Restrictions**: You are not allowed to redistribute or share the `.mmdb` file directly with others.
   If you need others to access it, direct them to download it
   from [MaxMind's official site](https://www.maxmind.com/en/home).

2. **Registration for Access**: Users are required to register an account to access and download the database.

3. **Updates and Maintenance**: The GeoLite2-City database is updated weekly. If you want to ensure your portal has
   the most accurate geolocation data, you need to revisit the MaxMind website and download the latest version manually.

4. **Fair Usage and Compliance**: MaxMind emphasizes fair usage and compliance with export controls, laws, and
   regulations when using the database. Please check GeoLite2 End User License Agreement
   for [details](https://www.maxmind.com/en/geolite2/eula).

### Practical Note:

- For any issues, visit MaxMind's [Support Center](https://support.maxmind.com/) for any support related with GeoLite2
  database.

---

By following these steps and adhering to the licensing requirements, you should have the `GeoLite2-City.mmdb` file ready
for use in your portal.
