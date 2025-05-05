# 🛑 Upgrade Guide: Important Instructions for Future Updates

## Table of Contents

1. [⚠️ Critical Warning: Read Before You Upgrade](#-critical-warning-read-before-you-upgrade-)
2. [General Upgrade Path Guidelines](#general-upgrade-path-guidelines)
3. [Upgrade Path Matrix](#upgrade-path-matrix)
4. [Release-Specific Notes: Version 1.7](#release-specific-notes-version-17)
5. [Upgrade Checklist](#upgrade-checklist)
6. [Step-by-Step Procedure](#step-by-step-procedure)
7. [Troubleshooting & Rollback](#troubleshooting--rollback)
8. [Additional Resources](#additional-resources)

---

# ⚠️🛑 **CRITICAL WARNING: READ BEFORE YOU UPGRADE** 🛑⚠️

> **🚨 FAILURE TO FOLLOW INSTRUCTIONS MAY RESULT IN:**
> - **Data Loss** 💾
> - **Significant Downtime** ⌛
> - **Irreversible System Errors** ❗
>
> ### Key Precautions:
> - **Check Compatibility:**  
    >   Verify the target environment meets all requirements, including:
    >   - Minimum PHP version
    >

- MySQL version

> - Additional system dependencies
> - **Follow Intermediate Steps Carefully:**  
    >   Skipping required intermediate versions or commands can break the upgrade process.
> - **Review System Configurations:**  
    >   Ensure system adjustments are made where necessary before proceeding.

**⚡ TAKE THIS SERIOUSLY ⚡**: Ignoring these steps could render your system unusable. Proceed with caution and always
create backups before upgrading.

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

## Upgrade Path Matrix

| Current Version | Intermediate Version | Target Version | Notes                                                             |
|-----------------|----------------------|----------------|-------------------------------------------------------------------|
| Below 1.5       | Follow earlier paths | 1.7.1          | Ensure compatibility with earlier versions before upgrading.      |
| 1.5             | 1.6                  | 1.7.1          | Run `php bin/console reset:allocate-providers` before proceeding. |
| 1.6             | N/A                  | 1.7.1          | Proceed directly to 1.7.1 after reviewing changelog.              |

Use this table to determine the exact steps based on your current version.

---

## Release-Specific Notes: Version 1.7

- **Deprecated Commands:**  
  The following commands or fields are no longer available in version **1.7**:
    - `reset:allocate-providers`
    - Deprecated fields: `googleId`, `saml_identifier`  
      These must be resolved at version **1.6** before proceeding to version **1.7**.

- **Database Schema Changes:**  
  In version **1.7**, schema changes require running:
  ```bash
  php bin/console doctrine:schema:update --force
  ```

- **Breaking Changes:**  
  Module configurations or data models from older versions may need to be adjusted. Refer to
  the [CHANGELOG.md](../CHANGELOG.md).

---

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

### Example: Upgrade to Version 1.7

**Scenario**: Your current version is **1.5** and you want to upgrade to **1.7**.

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
   > **Note:** This command is removed in version **1.7**, so it must be run in **1.6**.

4. **Proceed to Version 1.7**  
   After completing the steps above, **upgrade to version 1.7** following the usual procedure.

---

## Troubleshooting & Rollback

| Issue                              | Cause                                | Solution                                                      |
|------------------------------------|--------------------------------------|---------------------------------------------------------------|
| Missing `reset:allocate-providers` | Skipped upgrade to version 1.6       | Ensure intermediate upgrades are completed correctly.         |
| Database schema mismatch           | Schema updates not applied           | Run `php bin/console doctrine:schema:update --force`.         |
| Deprecation warnings               | Unresolved deprecated fields         | Resolve deprecated fields in version 1.6.                     |
| Cache-related issues               | Old cache files causing conflicts    | Run `php bin/console cache:clear` to rebuild the cache.       |

### Rollback Procedure

If any step fails:

1. Restore the full backup you created earlier.
2. Review the logs or errors to identify the failure point.
3. Perform the necessary fixes and repeat the upgrade steps.

---

## Additional Resources

- [CHANGELOG.md](../CHANGELOG.md)
