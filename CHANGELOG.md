# Changelog

# Release V1.8.0

- Update API for version 2, fix big for iOS App's with invalid format for profile generation
- Prometheus Implementation
- Fix bug with registration links, use could use them to re-log in to the portal at any time, can only be used once.
- Fix bug with account deletion, the admin was able to access the page using the url. The admin cannot delete his own account.
- Fix bug on the pagination page with the table `Access Points Usage` on the `dashboard/statistics/freeradius` page (Add
  new custom display of results per page).
- Fix bug about when the user session should be restored. Only when the firewall "landing".
- Invalidate session on the dashboard in case the admin changes is password on the landing firewall.
- Fix bug with return detector for expired links on registration email, now it returns to the login page with the input
  pre-fielded.
- For security reasons, 2FA is now required to be configured for admin users; now the dashboard is no longer assessable
  without it.
- For security reasons,`UserAccountDeletion` now simulates a login to confirm the account action for external providers.
- Fix bug with the forgot-password request, checks if the user is not verified and skips that extra unnecessary steps to
  avoid many codes and interactions with the user.
- Fix bug for capport endpoint, it's now independent of the API. Required for AP's configuration with captive portal
- For security reasons, the `ForgotPasswordRequest` process was reworked: email-based resets now require confirming a
  link before any database changes occur, and SMS-based resets require validating a code on a dedicated page before
  proceeding.
- New Setting for time configuration of email resend on the `ForgotPasswordRequest`, present on the Authentications
  methods page (EMAIL_TIMER_RESEND).
- New Setting for time configuration of an email link validly. This same time reflect for link present on the
  `ForgotPasswordRequest` & on the  `RegistrationWithEmail` (LINK_VALIDITY)
- Also for this release, it's required to run the new migrations to set up the new settings:
  Run the migrations with:

```bash
php bin/console doctrine:migrations:migrate
```

---

# Release V1.7.3

- Update docker add new geoLite volume, to save the previous geoLite database schema.
- Fix CAPPORT endpoint `/api/v1/capport/json` is independent of the current state of the `API_STATUS`.
- The user can now delete its own account from the account_widget popup
- New endpoint on the API to delete the user account for APP's
- Rework Two Factor Authenticator to have a type of validation. Now the page knows what type of request is being made
  when
  a new code is generated, to having problems of saving the previous number of attempts on new requests (disable,
  validate, verify, etc.)
- Rework Two Factor Authenticator request API endpoint to also now the type of request

---

# Release V1.7.2

- Fix minor detail with an invalid comparison to show the cookie banner on the landing page

---

# Release V1.7.1

- Removed the "Reset Password" option for admins editing their own account.
- Resolved an issue where logout didn't invalidate the session token, causing 2FA issues.
- Resolved an issue where editing a user account caused the ban action to also disable the account, which conflicted
  with the error messages in the landing page authenticator
- Migrated from the deprecated to its actively maintained forks:
    - [nbgrp/onelogin-saml-bundle](https://github.com/nbgrp/onelogin-saml-bundle) for ongoing support and updates.
    - [tetrapi/onelogin-saml-bundle](https://github.com/tetrapi/onelogin-saml-bundle) as an alternative with additional
      fixes for compatibility with Symfony 7.2.5 and deprecation warnings. `php-saml`
      `onelogin-saml-bundle`

- Fix validation for JWT tokens to prevent 500 errors during API authentication.
- Added validation on the **Authentication Methods** page, to check if the provider is active before submitting the
  page, to avoid conflicts with `PROFILE_LIMIT` date expiration.
- Fix turnstile validation on the login page was not triggering correctly.
- Update API docs add missing docs capport endpoint from "User Engagement Page"

---

# Release V1.7.0

- Update PHP to 8.4
- Add revoke reason everytime a profile is revoked
- Turnstile API Fix: Refactored the Turnstile logic in the API to resolve an issue where the verification step was being
  prematurely interrupted
- Rework **cookies integration only EEA users** (checks for current location of the user to show the cookies banner) -
  Using
  GeoLite2 from Maxmind
- New docs for GEOLITEGUI and setup
- New Setting for API Status (ON & OFF)
- SideBar Admin UI changes
- Two-Factor Authentication Implementation
    - New endpoint for 2FA request codes
    - Rework old endpoints authentication endpoint (local/google/saml/microsoft)to implement with 2FA
    - New settings page **/dashboard/settings/twoFA**
    - New implementation on landing page depending on the enforcement level
        - NOT_ENFORCED
        - ENFORCED_FOR_LOCAL
        - ENFORCED_FOR_ALL
    - New Two-factor authentication selection
        - Email
        - SMS
        - TOTP (Google Authenticator && Microsoft Authenticator)
- Microsoft Login Implementation - New authentication provider / New endpoint

> **Important**: In this release, the fields googleId, saml_identifier and Allocate Providers Command were eliminated.
> If you have version 1.5 or lower with data in these fields, you will have to first switch to version 1.6,
> run the Allocate Providers Command and then can you upgrade to version 1.7.

- **Note**: The Allocate Providers Command has been discontinued and has therefore been removed

```Bash
php bin/console reset:allocate-providers
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

- Rework User delete - Add PGP encryption (Steps for configuration on the [Installation Guide](docs/INSTALATION.md),
  it's
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
