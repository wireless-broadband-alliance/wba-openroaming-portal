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

