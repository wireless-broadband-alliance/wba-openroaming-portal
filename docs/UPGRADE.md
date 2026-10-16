# Upgrade Guide: Important Instructions for Future Updates

## Table of Contents

1. [Critical Warning: Read Before You Upgrade](#critical-warning-read-before-you-upgrade)
2. [General Upgrade Path Guidelines](#general-upgrade-path-guidelines)
3. [Upgrade Path Matrix](#upgrade-path-matrix)
4. [Upgrade Checklist](#upgrade-checklist)
5. [Step-by-Step Procedure](#step-by-step-procedure)
6. [Release-Specific Notes: Version 1.15.0](#release-specific-notes-version-1150)
7. [Release-Specific Notes: Version 1.14.2](#release-specific-notes-version-1142)
8. [Release-Specific Notes: Version 1.14.1](#release-specific-notes-version-1141)
9. [Release-Specific Notes: Version 1.14.0](#release-specific-notes-version-1140)
10. [Release-Specific Notes: Version 1.13.0](#release-specific-notes-version-1130)
11. [Release-Specific Notes: Version 1.8.1](#release-specific-notes-version-181)
12. [Release-Specific Notes: Version 1.7.x](#release-specific-notes-version-17x)
13. [Troubleshooting & Rollback](#troubleshooting--rollback)
14. [Additional Resources](#additional-resources)

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

| Current Version | Target Version | Required Actions                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
|-----------------|----------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `< 1.4.0`       | 1.4.0          | Run `php bin/console clear:eventEntity` to remove invalid legacy records before continuing.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 1.4.0           | 1.7.0          | Run `php bin/console lexik:jwt:generate-keypair` **before upgrading**.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| 1.5.0           | 1.7.0          | Run `php bin/console reset:allocate-providers` before proceeding.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| 1.6.0           | 1.7.0          | No additional steps required; proceed directly after reviewing the changelog.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| 1.7.x           | 1.8.0          | Run `php bin/console doctrine:schema:update --force` to apply required schema changes.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ≤ 1.8.0         | 1.8.1          | Run `php bin/console doctrine:migrations:migrate` to apply optimizations and remove the deprecated `verificationCode` field.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 1.8.1           | 1.9.0          | Run `php bin/console doctrine:migrations:migrate`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| 1.9.0           | 1.9.1          | Minor patch release — no commands required.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 1.9.1           | 1.10.0         | Run `php bin/console doctrine:migrations:migrate`. After upgrading, run `php bin/console prepare-release:v1100` **once** to migrate existing administrator permissions to the new Super Admin role hierarchy. Then fix VichUploader cache permissions: `chown -R www-data:www-data /var/www/openroaming/var/cache/prod/vich_uploader && chmod -R 777 /var/www/openroaming/var/cache/prod/vich_uploader`. All actions should be performed while the portal is **offline or restricted**.                                                                                                                                                                                                                                                                      |
| 1.10.x          | 1.11.0         | No migrations required. Update `.env` file: change `serverVersion=8` to `serverVersion=8.0.44` (or your actual MySQL version) in both `DATABASE_URL` and `DATABASE_FREERADIUS_URL`. Refer to `.env.sample` for reference.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 1.11.0          | 1.11.1         | Run `php bin/console doctrine:migrations:migrate`. To remove outdated settings from the database.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| 1.11.1          | 1.11.2         | No migrations required. Font files for Inter are now self-hosted under `public/fonts/inter/`. No additional steps required.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 1.11.2          | 1.12.0         | Run `php bin/console doctrine:migrations:migrate` to apply new configuration settings.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| 1.12.0          | 1.12.1         | Updated configuration variables default value to follow better practices (bool instead of ON & OFF, please consult the `env.sample` for more details).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| 1.12.1          | 1.13.0         | Run `php bin/console doctrine:migrations:migrate` to set up the new `AccessPoint`, `Network`, and `SMSProvider`/`SMSProviderParam` tables. Then run `php bin/console prepare:multiSMSMigration` **once** to migrate existing BudgetSMS API credentials from the `Settings` table to the new dedicated `SMSProvider` management system. Perform both actions while the portal is **offline or restricted**.                                                                                                                                                                                                                                                                                                                                                   |
| 1.13.0          | 1.13.1         | No migration or actions required.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| 1.13.1          | 1.14.0         | Run `php bin/console doctrine:migrations:migrate`. Next, run `php bin/console app:cra:migrate-all --yes` to execute all CRA data encryption steps at once (including Installation Progress data). Then complete the mandatory **Security.txt Configuration** step in the Installation Wizard. **Breaking:** API v1 and v2 are removed (use `/api/v3`) and the JWT TTL is now 15 minutes. Review your `.env` and Docker Compose setup (see [Release-Specific Notes: Version 1.14.0](#release-specific-notes-version-1140)). Perform these actions while the portal is **offline or restricted**.                                                                                                                                                              |
| 1.14.0          | 1.14.1         | Patch release — no database migrations or configuration changes required. Fixes Windows Wi-Fi profile XML signing failures (`xmlsec1`).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| 1.14.1          | 1.14.2         | Patch release — no database migrations or configuration changes required. Fixes Root CA SHA-1 hash formatting for Windows EAPHost schema validation, installation wizard `.env` sync, OAuth controller wiring, API user registration flows, and password reset token verification logic.                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `< 1.14.0`      | 1.15.0         | Not supported directly. Upgrade to 1.14.2 first and complete its steps (see the 1.14.0 notes), then follow the 1.15.0 row below.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| 1.14.2          | 1.15.0         | Create the production vault and `ENCRYPTION_KEY` (`php bin/console secrets:generate-keys --env=prod`, then `secrets:set ENCRYPTION_KEY - --env=prod`). Then run `php bin/console prepare-release:v1150 --dry-run` followed by `php bin/console prepare-release:v1150` **once**: it applies the database migrations, re-encrypts all data with the vault key and encrypts phone numbers. Afterwards remove `APP_SECRET` from `.env`, store a new one in the vault (`secrets:set APP_SECRET --random --env=prod`) and clear the cache. Back up `config/secrets/prod/`. Perform all actions while the portal is **offline or restricted** and with a fresh database backup. See [Release-Specific Notes: Version 1.15.0](#release-specific-notes-version-1150). |

---

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

## Release-Specific Notes: Version 1.15.0

**Scenario**: Your current version is **1.14.2** (or an earlier 1.14.x), and you want to upgrade to **1.15.0**.

> **Important:** This release re-encrypts existing data in place with a new key. Perform every step below while the
> portal is **offline or restricted**, with a fresh **database backup** and a **copy of your current `.env`** (it holds
> the legacy `APP_SECRET` that the migration needs).
>
> If you are on a version older than 1.14.0, upgrade to **1.14.2** first and complete its steps. Do not jump straight
> to 1.15.0.

### 1. What Changes

* Data encrypted at rest (sensitive settings, Installation Progress values, RADIUS tokens, TOTP/2FA secrets, SMS
  provider parameters) is now protected by a dedicated `ENCRYPTION_KEY` stored in the Symfony secrets vault instead of
  `APP_SECRET`. Encrypted values are stored with a `v2:` prefix.
* User phone numbers are now encrypted at rest. The `User.phoneNumber` column is widened to `VARCHAR(512)`, and users
  can no longer be searched by phone number in SQL.
* There is no fallback key and no silent plain-text fallback any more. If `ENCRYPTION_KEY` is missing or wrong, the
  portal fails loudly instead of reading or writing unprotected data.
* A single command, `prepare-release:v1150`, performs all the one-time data steps.

### 2. Required Actions

Run the commands from your portal directory. `docker compose exec` opens an interactive terminal by default, which the
migration needs for the hidden `APP_SECRET` prompt (do not add `-T`).

1. **Back up** the database and your current `.env`.

2. **Update and restart the containers** (set the new image tag in `docker-compose.yml`, then):

   ```bash
   docker compose pull
   docker compose up -d
   ```

3. **Create the production vault and `ENCRYPTION_KEY`** (once per installation):

   ```bash
   docker compose exec web php bin/console secrets:generate-keys --env=prod
   docker compose exec web sh -c 'openssl rand -hex 32 | php bin/console secrets:set ENCRYPTION_KEY - --env=prod'
   ```

   This creates the files in `config/secrets/prod/`. Two of them need your attention:

    * `prod.decrypt.private.php` unlocks every secret in the vault. **Never commit it or share it.** It must survive
      container re-creation (mount `./config/secrets/prod/` as a volume of the `web` service) and must be part of your
      backups.
    * If you lose the `ENCRYPTION_KEY`, the encrypted data **cannot be recovered**. Keep a copy of the whole
      `config/secrets/prod/` directory in a safe place, separate from the database backup.

   Do not define `ENCRYPTION_KEY` in `.env` or in the container environment as well: a real environment variable
   overrides the vault.

4. **Rehearse** the data steps. Nothing is written in a dry run:

   ```bash
   docker compose exec web php bin/console prepare-release:v1150 --dry-run
   ```

   When asked, paste your **current** `APP_SECRET` (the value in your `.env`). The input is hidden, so nothing is shown
   while you type. The report lists, per column, how many values would be re-encrypted, how many plain-text values would
   be encrypted and how many are skipped. If it stops with `Unable to decrypt value (wrong secret or corrupted data)`,
   the `APP_SECRET` you entered is not the one that encrypted the data. Nothing was changed.

5. **Run the migration for real**:

   ```bash
   docker compose exec web php bin/console prepare-release:v1150
   ```

   Confirm that the portal is offline and that you have a backup, then paste the `APP_SECRET` again. The command runs,
   in this order: a vault preflight check, the database migrations, `app:cra:migrate-to-vault` (one all-or-nothing
   transaction) and `app:cra:encrypt-phone-numbers`. If a step fails, nothing after it is run: fix the cause and run the
   command again. Every step is safe to repeat.

6. **Clear the cache**:

   ```bash
   docker compose exec web php bin/console cache:clear
   ```

7. **Replace `APP_SECRET`**. After a successful migration it no longer protects any stored data. Remove the
   `APP_SECRET=` line from `.env` (and from any Docker environment that defines it), then store a new random value in
   the vault and restart:

   ```bash
   docker compose exec web php bin/console secrets:set APP_SECRET --random --env=prod
   docker compose up -d --force-recreate web
   ```

   > **Note:** This logs every user out and invalidates existing remember-me cookies, signed URLs and issued API
   > tokens. This is expected.

### 3. Post-Upgrade Verification

* [ ] `prepare-release:v1150` finished with `All steps completed`, and running it a second time changes nothing.
* [ ] Logins with local accounts, Google, Microsoft, SAML, 2FA and TOTP work for existing users.
* [ ] The Installation Wizard, the LDAP settings and the Cloudflare certificate settings open without errors.
* [ ] SMS providers send messages, and user phone numbers are displayed correctly in the dashboard and in the user
  export.
* [ ] `phoneNumber` values in the `User` table are base64 text (about 60 characters), no longer readable numbers.
* [ ] `APP_SECRET` is no longer defined in `.env`, and `ENCRYPTION_KEY` appears in
  `php bin/console secrets:list --env=prod` (the values stay hidden unless you pass `--reveal`).
* [ ] A copy of `config/secrets/prod/` is stored in your backups.

### 4. Rollback

The old version cannot read data written by this release. To go back, restore the database backup **together with** the
previous `.env` and the previous image tag. Rolling back only the code leaves the portal unable to read the encrypted
data.

### 5. For Developers

* `php bin/console secrets:generate-keys` and `secrets:set ENCRYPTION_KEY` create the **dev** vault in
  `config/secrets/dev/`. The encrypted secrets and the public key are committed so the whole team shares them, but
  decryption keys (`config/secrets/*/*.decrypt.private.php`) are git-ignored for **every** environment and must never
  be committed. Get the dev decryption key from a teammate, or provide it as the `SYMFONY_DECRYPTION_SECRET`
  environment variable (base64 of the key file).
* The test environment does not use the dev vault. Define a throwaway `ENCRYPTION_KEY` (32+ characters) in `.env.test`.

> **Breaking Changes:**
> Please always review the [CHANGELOG.md](../CHANGELOG.md) for detailed information.

---

## Release-Specific Notes: Version 1.14.2

**Scenario**: Your current version is **1.14.1** (or earlier 1.14.x), and you want to upgrade to **1.14.2**.

### 1. Patch Release (No Schema/Database Migrations)

This maintenance release consolidates fixes for Windows Wi-Fi provisioning and XML profile signing (`xmlsec1`), OAuth
controller service wiring, API user registration flows, password reset token verification, installation wizard `.env`
synchronization, and administrative settings forms. No manual data migrations, database updates, or configuration
changes are required.

### 2. What Was Fixed

* **Windows EAPHost Root CA Hash Formatting:** Resolved an issue where continuous 40-character SHA-1 thumbprints
  extracted by the certificate wizard caused Windows Wi-Fi provisioning (`ms-settings:wifi-provisioning`) to fail with
  an "Unexpected error" ("Erro inesperado"). SHA-1 hashes are now automatically sanitized into 20 space-separated
  2-character hex pairs (e.g., `ca bd 2a 79...`) to comply with Windows EAPHost XML schema requirements.

* **XML Profile Signing (`xmlsec1`):** Fixed missing input/output parameters, undefined path variables, and added
  dynamic version checking so `--lax-key-search` is only passed on supported `xmlsec1` builds (1.3.0+), preventing
  command failures on older server environments.

* **Signature Algorithm Matching:** Added dynamic OpenSSL private key inspection to automatically match XML
  `<SignatureMethod>` and `<DigestMethod>` URIs (`rsa-sha256`) when using RSA certificates.

* **OAuth & Controller Wiring:** Fixed invalid dependency injection service wiring for `GoogleController` and
  `MicrosoftController` in `services.yaml` that caused `TypeError` 500 errors. Fixed external provider onboarding and
  payload validation in API registration endpoints.

* **Password Reset & Token Verification:** Fixed password reset emails containing hashed tokens instead of plain-text
  verification codes. Corrected API validation logic and `/forgot-password/link` verification to properly compare raw
  incoming tokens against Argon2id hashes stored in the database.

* **Installation Wizard & `.env` Sync:** Fixed a bug where `.env` settings were not updating database values following
  post-login listener verification during active installation flows. Updated settings scripts to safely quote and escape
  special shell characters (such as `!` and `$`) in secrets/passphrases.

* **PRR Settings Input Handling:** Fixed a `TypeError` when submitting PRR settings in Link mode or with empty text
  fields by ensuring empty submissions are converted safely before passing to the HTML sanitizer.

### 3. Upgrade Steps

1. Pull the latest code:
   ```bash
   git pull
   ```
2. Restart the containers:
   ```bash
   docker compose up -d
   ```
3. Clear Symfony cache:
   ```bash
   docker compose exec web php bin/console cache:clear
   ```

---

## Release-Specific Notes: Version 1.14.1

**Scenario**: Your current version is **1.14.0**, and you want to upgrade to **1.14.1**.

### 1. Patch Release (No Schema/Database Migrations)

This maintenance release resolves an issue where `xmlsec1` failed with `KEY-NOT-FOUND` or process errors when
attempting to sign Windows profiles (`template.xml` / `template_win11.xml`). No manual data migrations, database
updates, or configuration changes are required.

### 2. What Was Fixed

* Fixed missing input/output parameters and undefined file path variables passed to the Symfony `Process` execution
  pipeline.
* Added dynamic OpenSSL private key inspection to automatically match XML `<SignatureMethod>` and `<DigestMethod>`
  URIs (`rsa-sha256`) when using RSA certificates.
* Introduced `--lax-key-search` during `xmlsec1` execution to ensure smooth verification against provided PEM
  certificate chains.

### 3. Upgrade Steps

1. Pull the latest code:
   ```bash
   git pull
   ```
2. Restart the containers:
   ```bash
   docker compose up -d
   ```
3. Clear Symfony cache:
   ```bash
   docker compose exec web php bin/console cache:clear
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
> `app:cra:encrypt-installation-progress` skips any field that is empty or already looks encrypted, so re-running it is
> a no-op unless new plaintext data is found (pass `--force` on that command only if you specifically need to
> re-encrypt already-encrypted values).

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

### 6. Other Fixes in This Release

* Resetting a password from the dashboard no longer fails for users created via Magic Link and later promoted to admin.
* Fixed a 500 error caused by a non-existent admin permission value (`ACTIVITY_LOGS_WRITE`). No action required.

### 7. Post-Upgrade Verification

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

### 1. New Entities & Required Migrations

This release introduces the Coverage Map feature (`AccessPoint`, `Network` entities) and the new multi-provider
`SMSProvider`/`SMSProviderParam` management system. You must run:

```bash
php bin/console doctrine:migrations:migrate
```

### 2. Required One-Time Action — SMS Credentials Migration

After the schema migration above, run the following command **once** to migrate your existing BudgetSMS API
credentials from the `Settings` table into the new `SMSProvider` system:

```bash
php bin/console prepare:multiSMSMigration
```

> **Important:** This command must be executed only once, and while the portal is **offline or restricted**. Once the
> migration is complete, SMS provider credentials are managed exclusively through the new SMS Provider management page
> in the dashboard.

### 3. Post-Upgrade Notes

* No further manual steps are required for the Coverage Map, FreeRADIUS Statistics optimization, user data export,
  or TOTP/2FA changes in this release — these are available immediately after the migration completes.
* Please always review the [CHANGELOG.md](../CHANGELOG.md) for detailed information on breaking changes.

---

## Release-Specific Notes: Version 1.8.1

**Scenario**: Your current version is **1.8.0**, and you want to upgrade to **1.8.1**.

### 1. Removed Fields & Database Optimizations

In version **1.8.1**, the field `verificationCode` was removed as part of optimizations to improve account
verification and 2FA configuration.

> **Important:** If upgrading from **1.8.0** or lower and your database still contains the `verificationCode` field,
> ensure any necessary data migrations are handled before or during the upgrade.

### 2. Required Migrations

To apply the database optimizations and update the `User` entity schema, you must run:

```bash
php bin/console doctrine:migrations:migrate
```

> **Breaking Changes:**
> Please always review the [CHANGELOG.md](../CHANGELOG.md) for detailed information.

---

## Release-Specific Notes: Version 1.7.x

**Scenario**: Your current version is **1.5**, and you want to upgrade to **1.7.x**.

### 1. Create a Full Backup

Backup all critical data before proceeding:

* Database
* Configuration files
* User data

### 2. Upgrade to Version 1.6

If your current version is **1.5** or lower, first upgrade to **1.6**.

> **Important:** Skipping this step can break the upgrade path.

### 3. Run Pre-Upgrade Commands in Version 1.6

Execute the following command after upgrading to **1.6**:

```bash
php bin/console reset:allocate-providers
```

*This ensures deprecated fields (`googleId`, `saml_identifier`) are properly handled.*

> **Note:** This command is removed in version **1.7.x**, so it must be run in **1.6**.

### 4. Proceed to Version 1.7.x

After completing the steps above, upgrade to version **1.7.x** following the usual procedure.

---

## Troubleshooting & Rollback

| Issue                                                                                                              | Cause                                                                                                                                      | Solution                                                                                                                                                                        |
|--------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Errors running migrations                                                                                          | Invalid or old `Event` records                                                                                                             | Run `php bin/console clear:eventEntity` to clean up.                                                                                                                            |
| Missing JWT keypair                                                                                                | Skipped step during 1.4.0                                                                                                                  | Run `php bin/console lexik:jwt:generate-keypair`.                                                                                                                               |
| `reset:allocate-providers` missing                                                                                 | Skipped upgrade to 1.6                                                                                                                     | Upgrade to 1.6 and then run the command.                                                                                                                                        |
| Schema mismatch                                                                                                    | Schema updates not applied                                                                                                                 | Run `php bin/console doctrine:schema:update --force`.                                                                                                                           |
| Cache issues                                                                                                       | Stale or old cache files                                                                                                                   | Run `php bin/console cache:clear`.                                                                                                                                              |
| Installation Wizard shows up again after 1.14.0                                                                    | The migration resets `InstallProgress` to `IN_PROGRESS`                                                                                    | Expected behaviour. Complete the mandatory Security.txt Configuration step in the wizard.                                                                                       |
| API clients get 404 on `/api/v1` or `/api/v2`                                                                      | API v1 and v2 were removed in 1.14.0                                                                                                       | Update the integration to `/api/v3`.                                                                                                                                            |
| API tokens stop working after about 15 minutes                                                                     | JWT TTL reduced to 900s in 1.14.0                                                                                                          | Use the token refresh endpoints described in the API documentation.                                                                                                             |
| Users or admins cannot log in after the encryption                                                                 | An encryption step failed or ran partially                                                                                                 | Check the command output, then re-run `php bin/console app:cra:migrate-all --yes` (commands are safe to re-run).                                                                |
| Installation Wizard steps fail to decrypt after 1.14.0                                                             | `InstallationProgress` still holds plaintext values                                                                                        | Run `php bin/console app:cra:encrypt-installation-progress` (or the full `app:cra:migrate-all --yes` suite).                                                                    |
| `/metrics` returns 403 after 1.14.0                                                                                | `METRICS_ALLOWED_IPS` no longer defaults to `0.0.0.0/0`                                                                                    | Add your monitoring hosts to `METRICS_ALLOWED_IPS` in `.env`.                                                                                                                   |
| Windows Wi-Fi profile import fails with "Unexpected error" ("Erro inesperado")                                     | Unformatted Root CA SHA-1 thumbprint violating Windows EAPHost schema                                                                      | Upgrade to 1.14.2+ and re-save certificate settings to ensure the thumbprint is formatted as space-separated byte pairs (e.g., `ca bd 2a 79...`).                               |
| `Vault preflight failed` when running `prepare-release:v1150`, or `Environment variable not found: ENCRYPTION_KEY` | The production vault was not created, `config/secrets/prod/` is not available inside the container, or `ENCRYPTION_KEY` is missing from it | Create the vault with `secrets:generate-keys --env=prod` and `secrets:set ENCRYPTION_KEY - --env=prod`, and make sure `config/secrets/prod/` is mounted in the `web` container. |
| `Unable to decrypt value (wrong secret or corrupted data)` during `app:cra:migrate-to-vault`                       | The `APP_SECRET` typed at the prompt is not the one that encrypted the data                                                                | Nothing was changed (the command is all-or-nothing). Use the `APP_SECRET` from the `.env` of the version you are upgrading from and run the command again.                      |
| `Value is not in the v2 format` after upgrading to 1.15.0                                                          | Existing data was not migrated yet                                                                                                         | Run `php bin/console prepare-release:v1150` (it is safe to re-run).                                                                                                             |
| Everyone is logged out and API tokens stop working after replacing `APP_SECRET`                                    | `APP_SECRET` signs sessions, remember-me cookies and API token nonces                                                                      | Expected after rotating `APP_SECRET`. Users sign in again.                                                                                                                      |

### Rollback Procedure

If any step fails:

1. Restore the full backup you created before starting the upgrade.
2. Check logs or error messages to identify where the process failed.
3. Address the issue and repeat the upgrade process from the appropriate step.

---

## Additional Resources

- [CHANGELOG.md](../CHANGELOG.md)
