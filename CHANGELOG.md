# Changelog

---

# Release V1.5

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

# Release V1.4

- **Clear Event Command**: `clear:eventEntity` Removes any records in the `Event` entity that have empty or null fields.

> **Important**: This command will permanently delete any log or record in the `Event` entity that has an empty field.

For more details on how this command works, please refer to the file at:
**src/Command/ClearEventCommand.php**

- Rework Pagination on User Management Table
- User Provider Implementation (New UserExternalAuth Entity)
- Cleanup Admin UI (Options renamed to Settings -> Button now on the bottom of the page) in lower resolutions
- Fix number of user's per page in User Management Table
- PSR12 Implementation (Review all project for code reading optimization)

---

# Release Version 1.3

- **Allocate Providers Command**: `reset:allocate-providers` Allocates providers info from the **User
  Entity** to the **UserExternalAuth Entity**
- Rework User delete - Add PGP encryption (Steps for configuration on the [Installation Guide](INSTALATION.md))
- Forgot password for user's - landing page implementation (widget for user on the landing page after login)
- CloudFlare TurnStile Implementation - Landing page
- Update Landing Page UI - design update
- Update Admin Dashboard - design update

---

# Release Version 1.2.3

- Export data (Freeradius - Excel format)
- Export data (User Management - Excel format)
- Fix bugs with Freeradius Statistics (Fix data filtering)
- Fix saml authentication (account's without email)

---

# Release Version 1.2.2

- Portal Statistics (Graphics and statistics about the portal events)
- Freeradius Statistics (Graphics and statistics about the accounting of the users)

---

# Release Version 1.2.1

- Allow only white listed google domains to authenticate with Google
- Authenticate user after account creation with SMS provider
- Add blocker for code resending with SMS (block spam of code generation)

---

# Release Version 1.2.0

- Ban User's system (disable associated profiles)
- Verification User's system (also disable profiles)
- Events Rework with metadata info (json format conversion)
- Saml Provider Implementation

---

# Release Version 1.1

- Tailwind Implementation
- Login with Google implementation
- SMS Provider implementation (send sms)
- Admin UI Dashboard Management creation
- Capport Support
- Events Implementations

---

# Release Version 1.0

- Initial Release