# Security Policy & Coordinated Vulnerability Disclosure (CVD)

In compliance with the EU Cyber Resilience Act (CRA - Annex I, Sections 1 and 2.2) and RFC 9116, this document outlines
our security policies, vulnerability disclosure procedures, data protection measures and deployment security guidelines.

---

## 1. Supported Versions

Security fixes are only provided for the versions listed below. Operators running an older version must upgrade
following [UPGRADE.md](UPGRADE.md) before reporting an issue.

| Version  | Supported             |
|:---------|:----------------------|
| 1.15.x   | Yes                   |
| 1.14.x   | Security fixes only   |
| < 1.14.0 | No (upgrade required) |

---

## 2. Vulnerability Disclosure Policy

If you believe you have discovered a security vulnerability, please report it to us responsibly. Do **not** create
public issues, merge requests or discussions for security vulnerabilities.

### Who to Contact

- **Vulnerabilities in the OpenRoaming Provisioning Portal software itself:** email the maintainers at
  **security@REPLACE-WITH-VENDOR-DOMAIN**.
- **Issues with a specific deployment** (misconfiguration, exposed data on one portal): use the contact published in
  that portal's `/.well-known/security.txt`. Each operator configures their own contact in the Installation Wizard and
  in the **Coordinated Vulnerability Disclosure Configuration** section of the Admin Dashboard.

### How to Report

- **PGP Encryption:** We strongly encourage encrypting your submission using the PGP key referenced in the `Encryption`
  field of `/.well-known/security.txt`.
- **Information to Include:**
    - Affected version
    - Type of vulnerability (e.g., XSS, SSRF, Authentication Bypass)
    - Detailed steps to reproduce or a Proof of Concept (PoC)
    - Impact assessment

### Remediation SLAs (Response & Resolution Timelines)

| Stage                      | Target Timeline                                                                                     |
|:---------------------------|:----------------------------------------------------------------------------------------------------|
| **Initial Acknowledgment** | Within **24–48 hours**                                                                              |
| **Triage & Assessment**    | Within **5 business days**                                                                          |
| **Remediation / Patching** | **Critical:** 14 calendar days<br>**High:** 30 calendar days<br>**Medium/Low:** 60–90 calendar days |

Fixes are published as a new release, documented in the [CHANGELOG.md](CHANGELOG.md) and, when required, in
[UPGRADE.md](docs/UPGRADE.md).

---

## 3. Data Protection

### Encryption at Rest

Sensitive data stored in the database is encrypted with **AES-256-GCM**, using a dedicated `ENCRYPTION_KEY` stored in
the **Symfony secrets vault**. Encrypted values are stored with a `v2:` prefix. This covers:

- Sensitive settings (LDAP credentials, server endpoints, break-glass accounts, Cloudflare tokens)
- Installation Progress values (database URIs, Turnstile keys and secrets, JWT passphrase)
- RADIUS tokens
- SMS provider parameters
- 2FA / TOTP secrets
- User phone numbers

Values that never need to be read back are hashed instead:

- OTP backup codes: **Argon2id**
- OAuth provider IDs (Google and Microsoft): **HMAC-SHA256**

There is **no fallback key and no plain-text fallback**. If `ENCRYPTION_KEY` is missing or wrong, the portal fails
instead of reading or writing unprotected data.

### Secrets Management

- `ENCRYPTION_KEY` and `APP_SECRET` live in the Symfony secrets vault (`config/secrets/prod/`), not in `.env`.
- Do **not** also define `ENCRYPTION_KEY` in `.env` or in the container environment: a real environment variable
  overrides the vault.
- The vault decryption key (`config/secrets/*/*.decrypt.private.php`) must **never be committed or shared**. It is
  git-ignored for every environment.
- Mount `config/secrets/prod/` as a volume so it survives container re-creation, and keep a backup of it **separate
  from the database backup**.
- If the `ENCRYPTION_KEY` is lost, the encrypted data **cannot be recovered**.

### Data Retention (CRA Annex I §1.5)

The `clear:expired-user-data` command enforces data retention and minimization. It permanently purges:

- Soft-deleted users older than **30 days**, together with their associated records (`Event`, `DeletedUserData`,
  `OTPcode`, `UserRadiusProfile`)
- Expired 2FA / OTP codes (default **12 hours**, configurable on the Admin Dashboard)

Operators should schedule it as a cron job:

```bash
php bin/console clear:expired-user-data --yes
```

---

## 4. Technical Security & Hardening Guidelines

### Certificate Testing Utility – Security Consideration

The portal includes a **certificate testing utility** available in the admin interface:

`/dashboard/settings/certificatesManagement/radsecproxy/test`

This feature allows administrators to initiate outbound TLS connections to arbitrary IP addresses and ports from the
server.

#### Potential Risks

If misused or exposed to untrusted users, this functionality can be abused to:

- **Scan internal networks** by probing internal IP ranges and ports
- **Perform service discovery** based on TLS handshake responses
- **Bypass firewall restrictions** by leveraging the server as a trusted intermediary

#### Behavioral Note

- `Connection Refused` → Target port is closed
- `TLS Handshake Failed` → Target port is open and a service is responding

This behavior can unintentionally expose information about internal infrastructure.

#### Recommendations

- Restrict access to this feature to **trusted administrative users only**
- Apply **outbound firewall rules** to limit which destinations the server can contact

> This feature can be abused in a way similar to **Server-Side Request Forgery (SSRF)** if not properly secured.

---

### SSL Offloading & Transport Security

The OpenRoaming Provisioning Portal expects to be deployed behind an **SSL offloading load balancer or reverse proxy**
(e.g. Nginx, Cloudflare, HAProxy, Traefik).

#### Security Best Practices for Load Balancers & Proxies

1. **Strict Trusted Proxies Configuration**:
    - Only include exact IP addresses or subnets of your legitimate SSL offloading proxies in the `TRUSTED_PROXIES`
      environment variable.
    - **Do NOT** set `TRUSTED_PROXIES` to wildcard ranges like `0.0.0.0/0` in production environments, as attackers
      could spoof `X-Forwarded-*` headers (e.g. `X-Forwarded-Proto` or `X-Forwarded-For`), leading to HTTP header
      injection, IP spoofing, or bypass of transport security checks.

2. **Required Header Forwarding**:
    - Ensure your load balancer sanitizes incoming client headers and securely injects `X-Forwarded-Proto: https`,
      `X-Forwarded-For`, `X-Forwarded-Host`, and `X-Forwarded-Port`.

3. **HTTP Strict Transport Security (HSTS)**:
    - **Behind a proxy** (`openroaming.conf`): the web container's Nginx evaluates `X-Forwarded-Proto` and injects the
      `Strict-Transport-Security` header (`max-age=31536000; includeSubDomains; preload`) only when it is `https`.
    - **Direct SSL** (`openroaming-ssl.conf`): the header is always sent.

4. **Security Headers**:
    - Both Nginx configurations also send `Content-Security-Policy`, `X-Frame-Options: DENY`,
      `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`. If your proxy rewrites
      or strips response headers, make sure these are preserved.
