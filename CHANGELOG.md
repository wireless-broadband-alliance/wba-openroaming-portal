# Changelog
---

# Release V1.7.0

- Update PHP to 8.4
- Rework **cookies integration only EEA users** (checks for current location of the user to show the cookies banner) -
  Using
  GeoLite2 from Maxmind
- SideBar Admin UI changes

> **Important**: In this release, the fields googleId, saml_identifier and Allocate Providers Command were eliminated.
> If you have version 1.5 or lower with data in these fields, you will have to first switch to version 1.6,
> run the Allocate Providers Command and then can you upgrade to version 1.7.

- **Note**: The Allocate Providers Command has been discontinued and has therefore been removed

```Bash
php bin/console reset:allocate-providers
```

## Multiple Saml Providers Integration

Integrated support for Multiple SAML Providers with management features accessible via the UI

- **Set Default Saml Provider Command**: `app:set-saml-provider`. It ensures the default SAML provider is set, only if
  exist
  associated accounts that have a saml provider.

> **Important**: This command must be executed **before running any migrations**.

```bash
php bin/console app:set-saml-provider
```

# Release V1.6.0

- Fix bug on the date filtering on both statistics pages
- Add a country dropdown for phone selection
- Separate user and admin login flows
- Allow user or admin to revoke a radius profile
- Fix filtering results, they aren't reflect the actual active count on the filter's (Search User's)
- Cookie Banner
- TOS Checkbox to enable/disable buttons
- Allow TOS and Privacy Policy to be configured directly on the platform.
- New entity for TOS & Privacy Policy for the new custom editors on the terms page.
- Fix missing unit of measurement for statistics
- User per page on User Management is not reflecting the pagination
- Update READ.ME add new env's && settings
- Auto delete unconfirmed users after a specific timeframe configurable by the admin
- Added a new endpoint for Turnstile configuration required for the Android App's
- **Clear Unverified Accounts Command**: `clear:deleteUnconfirmedUsers` Removes any records in the `User` and all the
  associated entity's that have an unverified accounts associated.

> **Important**: This command will permanently delete any log or record in the `User` entity.

For more details on how this command works, please refer to the file at:
**src/Command/AutoDeleteUnconfirmedUsersCommand.php**

To use this command, run the following root in the root folder of the project:

```bash
php bin/console clear:deleteUnconfirmedUsers
```

- **Notify User When Profile is about to Expire Command**: `notify:usersWhenProfileExpires` Sends a notification
  (email/sms) for all the user's that have a profile installed when the expiration date is about to end.

For more details on how this command works, please refer to the file at:
**src/Command/NotifyUsersWhenProfileExpiresCommand.php**

To use this command, run the following root in the root folder of the project:

```bash
php bin/console notify:usersWhenProfileExpires
```

# Release V1.5.0

- Update Php version to (php8.3)
- Starting APIs (Open Api implementation - v4.0.2)
- Api docs generation (accessible in **dev** mode in "**/api**")
- Fix inputs validation on forms
- Fix Delete User (missing user_id with pgp_encryption)
- New user validation for profiles generation (isDisabled())
- Rework LDAP Command (conflicts with new php-ldap8.3 on old code)
- User Filter Tabs Search (All/Verified/Banned) fix counting
- Update && Review export user management && freeradius export (rework required with new UserExternalAuth entity)
- New events (about the new logic related with the api actions)

---

# Release V1.4.0

- **Clear Event Command**: `clear:eventEntity` Removes any records in the `Event` entity that have empty or null fields.

This command is required for older versions that cannot run the new migrations. The `clear:eventEntity` command removes
any records in the `Event` entity that have empty or null fields.

> **Important**: This command will permanently delete any log or record in the `Event` entity that has an empty field.

For more details on how this command works, please refer to the file at:
**src/Command/ClearEventCommand.php**

To use this command, run the following root in the root folder of the project:

```bash
php bin/console clear:eventEntity
```

- Rework Pagination on User Management Table
- User Provider Implementation (New UserExternalAuth Entity)
- Cleanup Admin UI (Options renamed to Settings -> Button now on the bottom of the page) in lower resolutions
- Fix number of user's per page in User Management Table
- PSR12 Implementation (Review all project for code reading optimization)

---

# Release V1.3.0

- **Allocate Providers Command**: `reset:allocate-providers` Allocates providers info from the **User
  Entity** to the **UserExternalAuth Entity**

> **Important**: This command will allocate any log or record in the `User` entity to the `UserExternalAuth` entity,

> For security reasons only run this command in older versions of the project to not miss any potential data

For more details on how this command works, please refer to the file at:
**src/Command/AllocateProvidersCommand.php**

To use this command, run the following code in the root folder of the project:

```bash
php bin/console reset:allocate-providers
```

- Rework User delete - Add PGP encryption (Steps for configuration on the [Installation Guide](INSTALATION.md), it's
  required to back up the user data for legal purposes)
- Forgot password for user's - landing page implementation (widget for user on the landing page after login)
- CloudFlare TurnStile Implementation - Landing page
- Update Landing Page UI - design update
- Update Admin Dashboard - design update

---

# Release V1.2.3

- Fix bugs with Freeradius Statistics (Fix data filtering)
- Fix SAML authentication (accounts without email)
- Export data (Freeradius - Excel format)
- Export data (User Management - Excel format)

---

# Release V1.2.2

- Add Portal Statistics (Graphics and statistics about the portal events)
- Add Freeradius Statistics (Graphics and statistics about the accounting of the users)

---

# Release V1.2.1

- Allow only white-listed Google domains to authenticate with Google
- Authenticate user after account creation with SMS provider
- Add blocker for code resending with SMS (block spam of code generation)

---

# Release V1.2.0

- Implement Ban User system (disable associated profiles)
- Implement Verification User system (also disables profiles)
- Events Rework with metadata info (json format conversion)
- Login with SAML Implementation

---

# Release V1.1.0

- Tailwind CSS Implementation
- Login with Google implementation
- SMS Provider implementation (send SMS)
- Admin UI Dashboard Management creation
- Capport Support/Implementation
- Events Implementations

---

# Release V1.0.0

- Initial Release
