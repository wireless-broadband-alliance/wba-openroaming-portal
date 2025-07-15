# 🛑 Upgrade Guide: Important Instructions for Future Updates

## Table of Contents

1. [⚠️ Critical Warning: Read Before You Upgrade](#-critical-warning-read-before-you-upgrade-)
2. [General Upgrade Path Guidelines](#general-upgrade-path-guidelines)
3. [Upgrade Path Matrix](#upgrade-path-matrix)
4. [Release-Specific Notes: Version 1.8.1](#release-specific-notes-version-181)
5. [Release-Specific Notes: Version 1.7](#release-specific-notes-version-17x)
6. [Upgrade Checklist](#upgrade-checklist)
7. [Step-by-Step Procedure](#step-by-step-procedure)
8. [Troubleshooting & Rollback](#troubleshooting--rollback)
9. [Additional Resources](#additional-resources)

---

# ⚠️🛑 **CRITICAL WARNING: READ BEFORE YOU UPGRADE** 🛑⚠️

> **🚨 FAILURE TO FOLLOW THESE INSTRUCTIONS MAY RESULT IN:**
> - **Data Loss** 💾
> - **Significant Downtime** ⌛
> - **Irreversible System Errors** ❗

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

**⚡ TAKE THIS SERIOUSLY ⚡**  
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
   versions.

5. **Proceed to the Target Version Upgrade**  
   Once prerequisites are met (backup, changelog review, and intermediate upgrades), you can safely upgrade to the
   target version.

---

## Upgrade Path Matrix

| Current Version | Intermediate Version | Target Version | Notes                                                                                                                  |
|-----------------|----------------------|----------------|------------------------------------------------------------------------------------------------------------------------|
| < 1.4.0         | 1.4.0                | 1.7.0          | Run `php bin/console clear:eventEntity` to clean up invalid records.                                                   |
| 1.4.0           | 1.5                  | 1.7.0          | Run `php bin/console lexik:jwt:generate-keypair` before upgrading.                                                     |
| 1.5             | 1.6                  | 1.7.0          | Run `php bin/console reset:allocate-providers` before proceeding.                                                      |
| 1.6             | N/A                  | 1.7.0          | Proceed directly to the release (1.7.0) after reviewing the changelog.                                                 |
| 1.7.x           | N/A                  | 1.7.0          | Run `php bin/console doctrine:schema:update --force` to apply required schema changes.                                 |
| 1.8.0 or lower  | N/A                  | 1.8.1          | Run `php bin/console doctrine:migrations:migrate` to apply optimizations & remove deprecated `verificationCode` field. |
| 1.8.0 or lower  | N/A                  | 1.9.0 (Latest) | Proceed directly to latest release after reviewing the changelog.                                                      |

Use this table to determine the exact upgrade steps based on your current version.

## Upgrade Checklist

Use the following checklist before starting the upgrade process:

- [ ] **Verify System Requirements**
    - Confirm the target environment matches all required dependencies (e.g., PHP, MySQL).

- [ ] **Create Backups**
    - Database
    - Configuration files
    - User data

- [ ] **Review Changelog**
    - Read release notes or [CHANGELOG.md](../CHANGELOG.md) for breaking changes or mandatory steps.

- [ ] **Complete Pre-Upgrade Steps**
    - Ensure your current version aligns with the **Upgrade Path Matrix**.
    - Run all pre-upgrade commands for intermediate versions.

---

## Step-by-Step Procedure

---

## Release-Specific Notes: Version 1.8.1

**Scenario**: Your current version is **1.8.0,** and you want to upgrade to **1.8.1**.

- **Removed Fields & Database Optimizations:**  
  In version **1.8.1**, the field `verificationCode` was removed as part of optimizations to improve account
  verification and 2FA configuration.
  > **Important:** If upgrading from **1.8.0** or lower and your database still contains the `verificationCode` field,
  ensure any necessary data migrations are handled before or during the upgrade.

- **Required Migrations:**  
  To apply the database optimizations and update the `User` entity schema, you must run:
  ```bash
  php bin/console doctrine:migrations:migrate

- **Breaking Changes:**  
  Please always review the [CHANGELOG.md](../CHANGELOG.md) for
  detailed information.

---

## Release-Specific Notes: Version 1.7.x

**Scenario**: Your current version is **1.5,** and you want to upgrade to **1.7.x**.

1. **Create a Full Backup**  
   Backup all critical data:
    - Database
    - Configuration files
    - User data

   This allows for rollback if issues arise.

2. **Upgrade to Version 1.6**
    - If your current version is **1.5** or lower, first upgrade to **1.6**.
   > **Important:** Skipping this step can break the upgrade path.

3. **Run Pre-Upgrade Commands in Version 1.6**  
   Execute the following command in your terminal after upgrading to **1.6**:
   ```bash
   php bin/console reset:allocate-providers
   ```  
   _This ensures deprecated fields (`googleId`, `saml_identifier`) are properly handled._
   > **Note:** This command is removed in version **1.7.x**, so it must be run in **1.6**.

4. **Proceed to Version 1.7.x**  
   After completing the steps above, **upgrade to version 1.7.x** following the usual procedure.

---

## Troubleshooting & Rollback

| Issue                              | Cause                          | Solution                                              |
|------------------------------------|--------------------------------|-------------------------------------------------------|
| Errors running migrations          | Invalid or old `Event` records | Run `php bin/console clear:eventEntity` to clean up.  |
| Missing JWT keypair                | Skipped step during 1.4.0      | Run `php bin/console lexik:jwt:generate-keypair`.     |
| `reset:allocate-providers` missing | Skipped upgrade to 1.6         | Upgrade to 1.6 and then run the command.              |
| Schema mismatch                    | Schema updates not applied     | Run `php bin/console doctrine:schema:update --force`. |
| Cache issues                       | Stale or old cache files       | Run `php bin/console cache:clear`.                    |

### Rollback Procedure

If any step fails:

1. Restore the full backup you created before starting the upgrade.
2. Check logs or error messages to identify where the process failed.
3. Address the issue and repeat the upgrade process from the appropriate step.

---

## Additional Resources

- [CHANGELOG.md](../CHANGELOG.md)
