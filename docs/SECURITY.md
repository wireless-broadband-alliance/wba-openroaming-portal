# Security Policy & Coordinated Vulnerability Disclosure (CVD)

In compliance with the EU Cyber Resilience Act (CRA - Annex I, Section 2.2) and RFC 9116, this document outlines our security policies, vulnerability disclosure procedures, and deployment security guidelines.

---

## 1. Vulnerability Disclosure Policy

If you believe you have discovered a security vulnerability, please report it to us responsibly. Do **not** create public GitHub issues or discussions for security vulnerabilities.

### How to Report
- **Email:** Contact us via the email specified in our `security.txt`.
- **PGP Encryption:** We strongly encourage encrypting your submission using the PGP key referenced in our `/.well-known/security.txt`.
- **Information to Include:**
   - Type of vulnerability (e.g., XSS, SSRF, Authentication Bypass)
   - Detailed steps to reproduce or a Proof of Concept (PoC)
   - Impact assessment

### Remediation SLAs (Response & Resolution Timelines)

| Stage | Target Timeline |
| :--- | :--- |
| **Initial Acknowledgment** | Within **24–48 hours** |
| **Triage & Assessment** | Within **5 business days** |
| **Remediation / Patching** | **Critical:** 14 calendar days<br>**High:** 30 calendar days<br>**Medium/Low:** 60–90 calendar days |

---

## 2. Technical Security & Hardening Guidelines

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

The OpenRoaming Provisioning Portal expects to be deployed behind an **SSL offloading load balancer or reverse proxy** (e.g. Nginx, Cloudflare, HAProxy, Traefik).

#### Security Best Practices for Load Balancers & Proxies

1. **Strict Trusted Proxies Configuration**:
   - Only include exact IP addresses or subnets of your legitimate SSL offloading proxies in the `TRUSTED_PROXIES` environment variable.
   - **Do NOT** set `TRUSTED_PROXIES` to wildcard ranges like `0.0.0.0/0` in production environments, as attackers could spoof `X-Forwarded-*` headers (e.g. `X-Forwarded-Proto` or `X-Forwarded-For`), leading to HTTP header injection, IP spoofing, or bypass of transport security checks.

2. **Required Header Forwarding**:
   - Ensure your load balancer sanitizes incoming client headers and securely injects `X-Forwarded-Proto: https`, `X-Forwarded-For`, `X-Forwarded-Host`, and `X-Forwarded-Port`.

3. **HTTP Strict Transport Security (HSTS)**:
   - The web container's Nginx configuration evaluates `X-Forwarded-Proto` and automatically injects the `Strict-Transport-Security` header (`max-age=31536000; includeSubDomains; preload`) when `X-Forwarded-Proto` is `https`.

