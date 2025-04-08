## 🛑 Upgrade Stop: Important Instructions for Future Updates

### General Upgrade Path Guidelines

1. **Review the Changelog**  
   Before starting any upgrade process, check & read the relevant release notes and on [CHANGELOG.MD](CHANGELOG.md).
   These file outline new features, breaking changes, deprecations, and mandatory steps.
   > **Tip**: The changelog is your most comprehensive source to discover mandatory pre-upgrade steps or new commands
   introduced.

2. **Upgrade to Required Intermediate Versions**  
   If you are using an older version, verify your system's eligibility for a direct upgrade. Some updates require your
   system to be upgraded to a specific intermediate version beforehand (e.g., `<intermediate_version>`). Skipping this
   step can break the upgrade path and result in data loss or corruption.

3. **Run Any Pre-Upgrade Commands**  
   Certain versions may introduce commands that must be executed before migrating to the new version. These commands
   can target schema changes, data migrations, or cleanup tasks that are mandatory for a successful upgrade.  
   Example command for version `<intermediate_version>`:
   ```bash
   <command_placeholder>
   ```  
   > **Note**: Commands removed or deprecated in the target version must be executed on the intermediate version to
   avoid migration failures or data inconsistencies.

4. **Backup Your System**  
   Always create a full backup of your system, including the database, configuration files, and user data, before
   initiating an upgrade. This precaution ensures that you can roll back in case of unforeseen issues.

5. **Proceed to the Target Version Upgrade**  
   After completing any required intermediate upgrades or commands, you can safely upgrade to the target version (e.g.,
   `<target_version>`). Ensure that all prior steps have been executed to avoid disruptions.

---

### Example: Upgrade to Version 1.7

If you are planning to upgrade to **version 1.7**, follow these specific steps:

1. **Upgrade to Version 1.6**  
   If your current version is **1.5** or lower, you **must** first upgrade your system to version **1.6**. This step is
   mandatory due to compatibility issues caused by changes in the application’s data model.

2. **Run the Allocate Providers Command**  
   After upgrading to version **1.6**, execute the following command in your terminal:
   ```bash
   php bin/console reset:allocate-providers
   ```  
   This command ensures that all deprecated fields (`googleId`, `saml_identifier`, and Allocate Providers) are handled
   properly before proceeding.
   > **Important**: This command was discontinued and removed in version **1.7**, so it must be executed in version *
   *1.6** before proceeding.

3. **Create a Full Backup**  
   Prior to upgrading to version **1.7**, create a complete backup of your system, including your database,
   configuration files, and user data, in case a rollback is needed.

4. **Proceed to Version 1.7**  
   After completing the above steps, proceed with upgrading your system to version **1.7**.
