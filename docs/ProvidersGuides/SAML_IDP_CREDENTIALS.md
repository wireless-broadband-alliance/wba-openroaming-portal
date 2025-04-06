# 🔒 SAML Specific Settings

Follow these steps to set up the **SAML Service Provider (SP)** and **Identity Provider (IdP)** properly:

---

## Step 1: Obtain the IdP Details
1. Contact your **Identity Provider (IdP)** administrator or refer to their documentation to gather the necessary details:
    - **IdP Entity ID**: The unique identifier for the IdP (usually a URI).
    - **IdP SSO URL**: The URL of the IdP's Single Sign-On Service endpoint.
    - **IdP X509 Certificate**: The certificate used by the IdP to sign SAML responses.

2. Ensure you have access to the IdP's administrative portal to manage connections or application configurations, if necessary.

---

## Step 2: Set Up Your SAML Service Provider (SP)
1. Identify your application's SAML **SP Entity ID** and **SP ACS URL**:
    - **SP Entity ID**: The unique identifier for your Service Provider (usually a URI).
    - **SP ACS URL**: The endpoint in your application to process SAML assertions from the IdP (e.g., `https://yourdomain.com/saml/acs`).

2. Configure these values in your IdP's settings to establish a trust relationship with your application:
    - Add a new **SAML Application** in the IdP portal.
    - Provide the **SP Entity ID** and **SP ACS URL** as required.

---

## Step 3: Obtain and Store the Required Variables
In your application's `.env` file (or in a `config/secrets` service if used), define the following variables:

```dotenv
# Identity Provider (IdP) Configuration
SAML_IDP_ENTITY_ID=your_idp_entity_id
SAML_IDP_SSO_URL=https://idp.sso.endpoint
SAML_IDP_X509_CERT=your_idp_x509_certificate

# Service Provider (SP) Configuration
SAML_SP_ENTITY_ID=your_sp_entity_id
SAML_SP_ACS_URL=https://yourdomain.com/saml/acs
```

### Example:
- **SAML_IDP_ENTITY_ID**: `https://idp.example.com/entity_id`
- **SAML_IDP_SSO_URL**: `https://idp.example.com/sso`
- **SAML_IDP_X509_CERT**: The Base64-encoded X509 certificate provided by your IdP.
- **SAML_SP_ENTITY_ID**: `https://yourapp.example.com/sp_entity_id`
- **SAML_SP_ACS_URL**: `https://yourapp.example.com/saml/acs`

---

## Step 4: Test the SAML Integration
1. Ensure that both the SP and IdP configurations match in your application and the IdP portal.
2. Use a test account from the IdP to log in to your application and verify the SAML SSO functionality.
3. Monitor SAML assertion logs for debugging, if necessary.

---

These variables enable the SAML-based single sign-on (SSO) integration for your application. Make sure these values are accurate and securely managed.
