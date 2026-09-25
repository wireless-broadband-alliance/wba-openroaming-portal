# Upgrade Guide: Important Instructions for Future Updates

## Table of Contents

1. [Critical Warning: Read Before You Upgrade](#critical-warning-read-before-you-upgrade)
2. [General Upgrade Path Guidelines](#general-upgrade-path-guidelines)
3. [Upgrade Path Matrix](#upgrade-path-matrix)
4. [Upgrade Checklist](#upgrade-checklist)
5. [Step-by-Step Procedure](#step-by-step-procedure)
6. [Release-Specific Notes: Version 1.14.0](#release-specific-notes-version-1140)
7. [Release-Specific Notes: Version 1.13.0](#release-specific-notes-version-1130)
8. [Release-Specific Notes: Version 1.8.1](#release-specific-notes-version-181)
9. [Release-Specific Notes: Version 1.7.x](#release-specific-notes-version-17x)
10. [Troubleshooting & Rollback](#troubleshooting--rollback)
11. [Additional Resources](#additional-resources)

---

# **CRITICAL WARNING: READ BEFORE YOU UPGRADE**

> **FAILURE TO FOLLOW THESE INSTRUCTIONS MAY RESULT IN:**
> - **Data Loss**
> - **Significant Downtime**
> - **Irreversible System Errors**

### Key Precautions

- **Check Compatibility**  
  Verify that your target environment meets all requirements, including:
    - Minimum PHP version
    - MySQL version
    - Additional system dependencies

- **Follow Intermediate Steps Carefully**  
  Skipping required intermediate versions or commands can break the upgrade process and leave your system in an unusable
  state.

- **Review System Configurations**  
  Ensure that all necessary system adjustments are made before proceeding with the upgrade.

**TAKE THIS SERIOUSLY**  
Ignoring these steps could render your system unusable. Proceed with caution and always create backups before upgrading.

---

## General Upgrade Path Guidelines

Upgrading your system requires caution and preparation. Follow these general guidelines in each upgrade process:

1. **Backup Your System**  
   Always create a **full backup** of your system, including:
    - Database
    - Configuration files
    - User data  
      Backups ensure that you can roll back in case of unforeseen issues.

2. **Review the Changelog**  
   Before upgrading, carefully read the release notes or [CHANGELOG.md](../CHANGELOG.md). This file outlines:
    - New features
    - Breaking changes
    - Deprecations
    - Mandatory upgrade steps
   > **Tip:** The changelog will help identify required pre-upgrade steps or commands for each version.

3. **Upgrade to Required Intermediate Versions**  
   If you're on an older version, determine if an intermediate upgrade is required. Skipping intermediate upgrades may:
    - Break the upgrade path
    - Cause data corruption

   Intermediate versions (e.g., `<intermediate_version>`) act as compatibility layers essential for successful
   upgrading.

4. **Run Any Pre-Upgrade Commands**  
   Some upgrades require running specific commands—such as schema changes or cleanup tasks—before proceeding to the next
   version.

   Example for `<intermediate_version>`:
   ```bash
   php bin/console <command_placeholder>
    ```

> **Note:** Ensure these commands are run in the correct version as they might be removed or deprecated in later
> versions.

5. **Proceed to the Target Version Upgrade**
   Once prerequisites are met (backup, changelog review, and intermediate upgrades), you can safely upgrade to the
   target version.

---

## Upgrade Path Matrix

Use this table to determine the exact upgrade steps based on your current version.

| Current Version | Target Version | Required Actions                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
|-----------------|----------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `< 1.4.0`       | 1.4.0          | Run `php bin/console clear:eventEntity` to remove invalid legacy records before continuing.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| 1.4.0           | 1.7.0          | Run `php bin/console lexik:jwt:generate-keypair` **before upgrading**.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| 1.5.0           | 1.7.0          | Run `php bin/console reset:allocate-providers` before proceeding.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| 1.6.0           | 1.7.0          | No additional steps required; proceed directly after reviewing the changelog.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 1.7.x           | 1.8.0          | Run `php bin/console doctrine:schema:update --force` to apply required schema changes.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ≤ 1.8.0         | 1.8.1          | Run `php bin/console doctrine:migrations:migrate` to apply optimizations and remove the deprecated `verificationCode` field.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| 1.8.1           | 1.9.0          | Run `php bin/console doctrine:migrations:migrate`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| 1.9.0           | 1.9.1          | Minor patch release — no commands required.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| 1.9.1           | 1.10.0         | Run `php bin/console doctrine:migrations:migrate`. After upgrading, run `php bin/console prepare-release:v1100` **once** to migrate existing administrator permissions to the new Super Admin role hierarchy. Then fix VichUploader cache permissions: `chown -R www-data:www-data /var/www/openroaming/var/cache/prod/vich_uploader && chmod -R 777 /var/www/openroaming/var/cache/prod/vich_uploader`. All actions should be performed while the portal is **offline or restricted**.                                                                                                                                                                          |
| 1.10.x          | 1.11.0         | No migrations required. Update `.env` file: change `serverVersion=8` to `serverVersion=8.0.44` (or your actual MySQL version) in both `DATABASE_URL` and `DATABASE_FREERADIUS_URL`. Refer to `.env.sample` for reference.                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| 1.11.0          | 1.11.1         | Run `php bin/console doctrine:migrations:migrate`. To remove outdated settings from the database.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| 1.11.1          | 1.11.2         | No migrations required. Font files for Inter are now self-hosted under `public/fonts/inter/`. No additional steps required.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| 1.11.2          | 1.12.0         | Run `php bin/console doctrine:migrations:migrate` to apply new configuration settings.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| 1.12.0          | 1.12.1         | Updated configuration variables default value to follow better practices (bool instead of ON & OFF, please consult the `env.sample` for more details).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| 1.12.1          | 1.13.0         | Run `php bin/console doctrine:migrations:migrate` to set up the new `AccessPoint`, `Network`, and `SMSProvider`/`SMSProviderParam` tables. Then run `php bin/console prepare:multiSMSMigration` **once** to migrate existing BudgetSMS API credentials from the `Settings` table to the new dedicated `SMSProvider` management system. Perform both actions while the portal is **offline or restricted**.                                                                                                                                                                                                                                                       |
| 1.13.0          | 1.13.1         | No migration or actions required.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| 1.13.1          | 1.14.0         | Run `php bin/console doctrine:migrations:migrate` (this also sets the installation progress back to `IN_PROGRESS`). Next, run `php bin/console app:cra:migrate-all --yes` to execute all CRA data encryption steps at once (including Installation Progress data). Then complete the mandatory **Security.txt Configuration** step in the Installation Wizard. **Breaking:** API v1 and v2 are removed (use `/api/v3`) and the JWT TTL is now 15 minutes. Review your `.env` and Docker Compose setup (see [Release-Specific Notes: Version 1.14.0](#release-specific-notes-version-1140)). Perform these actions while the portal is **offline or restricted**. |

Use this table to determine the exact upgrade steps based on your current version.

## Upgrade Checklist

Use the following checklist before starting the upgrade process:

* [ ] **Verify System Requirements**
* Confirm the target environment matches all required dependencies (e.g., PHP, MySQL).


* [ ] **Create Backups**
* Database
* Configuration files
* User data


* [ ] **Review Changelog**
* Read release notes or [CHANGELOG.md](../CHANGELOG.md) for breaking changes or mandatory steps.


* [ ] **Complete Pre-Upgrade Steps**
* Ensure your current version aligns with the **Upgrade Path Matrix**.
* Run all pre-upgrade commands for intermediate versions.

---

## Step-by-Step Procedure

Follow these general steps when updating the portal:

1. **Navigate to the project folder and pull the latest version**
   Navigate to the directory where the portal is installed on your server, then pull the latest version:

```bash
   cd /path/to/your/portal
   git pull
```

> **Note:** Replace `/path/to/your/portal` with the actual path where the portal is installed on your server.

2. **Pull the latest Docker images**

```bash
    docker compose pull
```

3. **Restart the containers**

```bash
    docker compose up -d
```

4. **Run any version-specific commands**
   Check the [Upgrade Path Matrix](#upgrade-path-matrix) for your current version and
   run any required commands inside the container. Example:

```bash
    docker compose exec web php bin/console doctrine:migrations:migrate
```

5. **Clear the cache**

```bash
    docker compose exec web php bin/console cache:clear
```

6. **Verify the portal is running correctly**
   Check logs for errors:

```bash
    docker compose logs -f web
```

---

## Release-Specific Notes: Version 1.14.0

**Scenario**: Your current version is **1.13.1** (or earlier 1.13.x), and you want to upgrade to **1.14.0**.

> **Important:** Perform the database migration, the CRA encryption and the wizard step below while the portal is
> **offline or restricted**, and make sure you have a fresh database backup. The encryption commands rewrite existing
> data in place.

### 1. Breaking Changes to Review Before Upgrading

* **API v1 and v2 have been permanently removed.**
  As announced in the [Release V1.9.0 deprecation notice](../CHANGELOG.md#api-deprecation-notice), all `/api/v1` and
  `/api/v2` routes no longer exist. **API v3 is the only supported API version.** Any client or integration still
  calling `/api/v1` or `/api/v2` must be updated to `/api/v3` **before** upgrading.

* **JWT token lifetime reduced (CRA Annex I §1.2).**
  The token TTL for the privileged IAM API dropped from 3600s (1 hour) to **900s (15 minutes)**, and token refresh
  endpoints were added. Integrations must use the refresh endpoints instead of relying on a token that lasts an hour.
  See the updated API documentation for details.

* **Metrics endpoint access restricted (CRA Annex I §1.7).**
  The `.env.sample` default for `METRICS_ALLOWED_IPS` used to be `0.0.0.0/0`, which allowed unrestricted access to the
  Prometheus metrics when enabled. Review the value in your own `.env` and restrict it to your monitoring hosts.

* **Password resets restricted.**
  Password resets are now limited to accounts created directly on the portal (not external providers).

* **Security hardening of the deployment.**
  This release also enforces mandatory TLS (HSTS and database `sslmode=verify-full`), binds services to localhost and
  externalizes database secrets in Docker Compose, tightens web server headers, request size and rate limits, and runs
  the container as a non-root user. Compare your `.env` and `docker-compose` files with the updated `.env.sample` and
  compose file, and confirm that your database connection supports TLS certificate verification before restarting.

### 2. Required Database Migrations

Run Doctrine migrations to apply the setting structure updates:

```bash
  php bin/console doctrine:migrations:migrate
```

> **Note:** This migration automatically changes the installation progress state (`InstallProgress`) from `COMPLETED`
> back to `IN_PROGRESS`. This is expected: the Installation Wizard will ask for the new mandatory step described in
> section 4.

### 3. Required One-Time Actions — Cyber Resilience Act (CRA Annex I §1.3, §1.5) Data Encryption

To comply with CRA data protection mandates, existing plain-text sensitive data in the database must be encrypted or
hashed.

**Option A: Master Encryption Suite (Recommended)**

Run the single master command to automatically execute all CRA encryption tasks in sequence (OAuth IDs, system
settings, Installation Progress URIs, RADIUS tokens, OTP codes, SMS parameters and 2FA data):

```bash
  php bin/console app:cra:migrate-all --yes
```

**Option B: Granular Step-by-Step Execution**

If you need to execute tasks individually, run the following commands in sequence:

1. **OAuth Provider IDs (Google and Microsoft `provider_id`, HMAC-SHA256 hashing):**
```bash
    php bin/console app:cra:hash-oauth-ids
```

2. **Sensitive System Settings (LDAP credentials, server endpoints, break-glass accounts, Cloudflare tokens,
   AES-256-CBC):**
```bash
    php bin/console app:cra:encrypt-settings
```

3. **Installation Progress Data (`dbOpenRoaming`, `dbFreeradius` database URIs, Cloudflare Turnstile keys/secrets and
   JWT passphrase, AES-256-GCM):**

```bash
    php bin/console app:cra:encrypt-installation-progress
```

4. **Legacy RADIUS Tokens (`radius_token`, AES-256-CBC):**
```bash
    php bin/console app:cra:encrypt-radius-legacy-tokens
```

5. **Legacy OTP Backup Codes (`OTPcode.code`):**
```bash
    php bin/console app:cra:hash-otp-codes
```

6. **SMS Legacy Parameters:**
```bash
    php bin/console app:cra:encrypt-legacy-sms-params
```

7. **2FA & TOTP Secrets (`User.twoFAsecret`, AES-256-CBC):**
```bash
    php bin/console app:cra:encrypt-2fa-data
```

> **Note:** All encryption commands are independent and safe to re-run if needed.
`app:cra:encrypt-installation-progress`
> skips any field that is empty or already looks encrypted, so re-running it is a no-op unless new plaintext data is
> found (pass `--force` on that command only if you specifically need to re-encrypt already-encrypted values).

### 4. Required Action — Security.txt Configuration (Coordinated Vulnerability Disclosure)

After the migration, log in as an administrator and complete the mandatory **Security.txt Configuration** step in the
Installation Wizard. It defines the Coordinated Vulnerability Disclosure (CVD) parameters:

* Security Contact
* Expiration Date
* PGP Fingerprint (optional)

After the initial setup, these parameters can be reviewed and updated at any time in the new **Coordinated
Vulnerability Disclosure Configuration** section of the Admin Dashboard.

### 5. Recommended — Schedule the Data Retention Cleanup (CRA Annex I §1.5)

The new `clear:expired-user-data` command enforces data retention and minimization. It permanently purges:

* Soft-deleted users older than the configured threshold (default: **30 days**), together with their associated
  records (`Event`, `DeletedUserData`, `OTPcode`, `UserRadiusProfile`).
* Expired 2FA/OTP codes older than the configured threshold (default: **12 hours**).

It is designed to run as a scheduled cron job, using the non-interactive option:

```bash
  php bin/console clear:expired-user-data --yes
```

> **Important:** This command permanently deletes data. Confirm the retention thresholds before scheduling it.

### 6. Post-Upgrade Verification

* [ ] The Installation Wizard was completed and the Security.txt configuration is saved.
* [ ] Integrations authenticate against `/api/v3` and handle the 15-minute JWT lifetime (token refresh).
* [ ] `METRICS_ALLOWED_IPS` only contains the intended monitoring hosts.
* [ ] The portal connects to both databases (OpenRoaming and FreeRADIUS) with the new TLS settings.
* [ ] Logins with Google, Microsoft, SAML, 2FA and TOTP still work for existing users.
* [ ] `dbOpenRoaming`, `dbFreeradius`, `turnstileKey`, `turnstileSecret` and `jwtPassphrase` on `InstallationProgress`
  are stored as encrypted values, not plaintext.

> **Breaking Changes:**
> Please always review the [CHANGELOG.md](../CHANGELOG.md) for detailed information.

---

## Release-Specific Notes: Version 1.13.0

**Scenario**: Your current version is **1.12.1**, and you want to upgrade to **1.13.0**.

* **New Entities & Required Migrations:**
  This release introduces the Coverage Map feature (`AccessPoint`, `Network` entities) and the new multi-provider
  `SMSProvider`/`SMSProviderParam` management system. You must run:

```bash
  php bin/console doctrine:migrations:migrate
```

* **Required One-Time Action — SMS Credentials Migration:**
  After the schema migration above, run the following command **once** to migrate your existing BudgetSMS API
  credentials from the `Settings` table into the new `SMSProvider` system:

```bash
  php bin/console prepare:multiSMSMigration
```

> **Important:** This command must be executed only once, and while the portal is **offline or restricted**. Once the
> migration is complete, SMS provider credentials are managed exclusively through the new SMS Provider management page
> in
> the dashboard.

* **No further manual steps** are required for the Coverage Map, FreeRADIUS Statistics optimization, user data export,
  or TOTP/2FA changes in this release — these are available immediately after the migration completes.
* **Breaking Changes:**
  Please always review the [CHANGELOG.md](../CHANGELOG.md) for detailed information.

---

## Release-Specific Notes: Version 1.8.1

**Scenario**: Your current version is **1.8.0,** and you want to upgrade to **1.8.1**.

* **Removed Fields & Database Optimizations:**
  In version **1.8.1**, the field `verificationCode` was removed as part of optimizations to improve account
  verification and 2FA configuration.

> **Important:** If upgrading from **1.8.0** or lower and your database still contains the `verificationCode` field,
> ensure any necessary data migrations are handled before or during the upgrade.

* **Required Migrations:**
  To apply the database optimizations and update the `User` entity schema, you must run:

```bash
  php bin/console doctrine:migrations:migrate

```

* **Breaking Changes:**
  Please always review the [CHANGELOG.md](../CHANGELOG.md) for detailed information.

---

## Release-Specific Notes: Version 1.7.x

**Scenario**: Your current version is **1.5,** and you want to upgrade to **1.7.x**.

1. **Create a Full Backup**
   Backup all critical data:

* Database
* Configuration files
* User data

This allows for rollback if issues arise.

2. **Upgrade to Version 1.6**

* If your current version is **1.5** or lower, first upgrade to **1.6**.

> **Important:** Skipping this step can break the upgrade path.

3. **Run Pre-Upgrade Commands in Version 1.6**
   Execute the following command in your terminal after upgrading to **1.6**:

```bash
   php bin/console reset:allocate-providers
```

*This ensures deprecated fields (`googleId`, `saml_identifier`) are properly handled.*

> **Note:** This command is removed in version **1.7.x**, so it must be run in **1.6**.

4. **Proceed to Version 1.7.x**
   After completing the steps above, **upgrade to version 1.7.x** following the usual procedure.

---

## Troubleshooting & Rollback

| Issue                                                  | Cause                                                   | Solution                                                                                                         |
|--------------------------------------------------------|---------------------------------------------------------|------------------------------------------------------------------------------------------------------------------|
| Errors running migrations                              | Invalid or old `Event` records                          | Run `php bin/console clear:eventEntity` to clean up.                                                             |
| Missing JWT keypair                                    | Skipped step during 1.4.0                               | Run `php bin/console lexik:jwt:generate-keypair`.                                                                |
| `reset:allocate-providers` missing                     | Skipped upgrade to 1.6                                  | Upgrade to 1.6 and then run the command.                                                                         |
| Schema mismatch                                        | Schema updates not applied                              | Run `php bin/console doctrine:schema:update --force`.                                                            |
| Cache issues                                           | Stale or old cache files                                | Run `php bin/console cache:clear`.                                                                               |
| Installation Wizard shows up again after 1.14.0        | The migration resets `InstallProgress` to `IN_PROGRESS` | Expected behaviour. Complete the mandatory Security.txt Configuration step in the wizard.                        |
| API clients get 404 on `/api/v1` or `/api/v2`          | API v1 and v2 were removed in 1.14.0                    | Update the integration to `/api/v3`.                                                                             |
| API tokens stop working after about 15 minutes         | JWT TTL reduced to 900s in 1.14.0                       | Use the token refresh endpoints described in the API documentation.                                              |
| Users or admins cannot log in after the encryption     | An encryption step failed or ran partially              | Check the command output, then re-run `php bin/console app:cra:migrate-all --yes` (commands are safe to re-run). |
| Installation Wizard steps fail to decrypt after 1.14.0 | `InstallationProgress` still holds plaintext values     | Run `php bin/console app:cra:encrypt-installation-progress` (or the full `app:cra:migrate-all --yes` suite).     |
| `/metrics` returns 403 after 1.14.0                    | `METRICS_ALLOWED_IPS` no longer defaults to `0.0.0.0/0` | Add your monitoring hosts to `METRICS_ALLOWED_IPS` in `.env`.                                                    |

### Rollback Procedure

If any step fails:

1. Restore the full backup you created before starting the upgrade.
2. Check logs or error messages to identify where the process failed.
3. Address the issue and repeat the upgrade process from the appropriate step.

---

## Additional Resources

- [CHANGELOG.md](../CHANGELOG.md)
