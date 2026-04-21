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
