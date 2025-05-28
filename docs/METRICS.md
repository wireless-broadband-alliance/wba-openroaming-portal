# Prometheus Metrics Configuration

This document provides information on how to configure the Prometheus metrics endpoint in the OpenRoaming Provisioning Portal.

## Environment Variables

Add the following variables to your `.env` file to configure the metrics endpoint:

```
###> prometheus-metrics ###
# Enable or disable the metrics endpoint (disabled by default)
METRICS_ENABLED=false

# Comma-separated list of IPs or CIDR ranges allowed to access metrics
# If not set, defaults to allowing all IPs (0.0.0.0/0)
METRICS_ALLOWED_IPS=127.0.0.1
###< prometheus-metrics ###
```

## Configuration Options

### Enabling/Disabling Metrics

The `METRICS_ENABLED` variable controls whether the metrics endpoint is available:

- `METRICS_ENABLED=true`: Metrics endpoint is enabled
- `METRICS_ENABLED=false`: Metrics endpoint is disabled and will return a 404 response (default)

### Restricting Access by IP Address

The `METRICS_ALLOWED_IPS` variable controls which IP addresses are allowed to access the metrics endpoint:

- Not set or empty: Allow access from any IP address (0.0.0.0/0)
- `METRICS_ALLOWED_IPS=127.0.0.1`: Allow access only from localhost
- `METRICS_ALLOWED_IPS=192.168.1.10`: Allow access only from the specific IP 192.168.1.10
- `METRICS_ALLOWED_IPS=10.0.0.0/8,192.168.1.0/24`: Allow access from multiple networks (comma-separated)

If an unauthorized IP attempts to access the metrics endpoint, a 403 Forbidden response will be returned.

## Available Metrics

The following metrics are available at the `/metrics` endpoint:

- **App Information**:
  - `app_info{version="x.x.x",environment="prod"}`: Information about the application version and environment

- **Users**:
  - `app_users_total{state="total"}`: Total number of users
  - `app_users_total{state="verified"}`: Number of verified users
  - `app_users_total{state="banned"}`: Number of banned users

- **Authentication Providers**:
  - `app_users_by_auth_provider{provider="Portal Account"}`: Users with Portal Account
  - `app_users_by_auth_provider{provider="SAML Account"}`: Users with SAML Account
  - `app_users_by_auth_provider{provider="Google Account"}`: Users with Google Account
  - `app_users_by_auth_provider{provider="Microsoft Account"}`: Users with Microsoft Account
  
- **Portal Account Types**:
  - `app_portal_users_by_type{type="Email"}`: Portal users with Email authentication
  - `app_portal_users_by_type{type="Phone Number"}`: Portal users with Phone Number authentication

- **Radius Profiles**:
  - `app_radius_profiles_total{status="X"}`: Number of radius profiles with status X
  - `app_radius_profiles_total{status="total"}`: Total number of radius profiles

## Prometheus Configuration

Here's an example Prometheus scrape configuration:

```yaml
scrape_configs:
  - job_name: 'openroaming-portal'
    metrics_path: '/metrics'
    static_configs:
      - targets: ['your-portal-hostname:80']
```

Replace `your-portal-hostname` with the hostname or IP address of your OpenRoaming Provisioning Portal. 