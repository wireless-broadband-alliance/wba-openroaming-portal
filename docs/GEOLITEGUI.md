# Steps to Download the GeoLite2-City.mmdb File from MaxMind

The GeoLite2-City database is distributed by MaxMind, and you need to follow these steps to download it successfully:

---

## 1. Register an Account on MaxMind

1. Navigate to the [MaxMind Sign Up page](https://www.maxmind.com/en/geolite2/signup).
2. Fill in the required details to create your MaxMind account.
3. Verify your email address by clicking the link in the verification email sent to your inbox.

---

## 2. Log in to Your MaxMind Account

1. After completing the registration and email verification, go to
   the [MaxMind Login page](https://www.maxmind.com/en/account/login).
2. Enter your registered email and password to access your account.

---

## 3. Download the GeoLite2-City.mmdb File

1. Once logged in, go to the [GeoLite2 Databases page](https://www.maxmind.com/en/geolite2).
2. Scroll down to the "GeoLite2 City" section.
3. Click the **Download** button. You may be required to agree to the licensing terms before the download starts.
4. Save the `.tar.gz` file to your preferred location.

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

- Copy the `GeoLite2-City.mmdb` file to the appropriate directory in your project.
- Ensure your application points to the correct file path for accessing the database.

---

## Notes

- The GeoLite2-City database is updated frequently. You may want to periodically download the latest version for
  accurate IP geolocation data.
- If you experience any issues, refer to the MaxMind [Support Center](https://support.maxmind.com/) for troubleshooting.

--- 

By following these steps, you should have the `GeoLite2-City.mmdb` file ready for use in your application.
