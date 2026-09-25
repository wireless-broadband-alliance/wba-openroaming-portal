# SAML Specific Settings

Follow these steps to set up the **SAML Service Provider (SP)** and **Identity Provider (IdP)** properly:

---

## Step 1: Obtain the IdP Details

1. Contact your **Identity Provider (IdP)** administrator or refer to their documentation to gather the necessary details:

* **IdP Entity ID**: The unique identifier for the IdP (usually a URI).
* **IdP SSO URL**: The URL of the IdP's Single Sign-On Service endpoint.
* **IdP X509 Certificate**: The certificate used by the IdP to sign SAML responses.

2. Ensure you have access to the IdP's administrative portal to manage connections or application configurations, if necessary.

---

## Step 2: Set Up Your SAML Service Provider (SP)

1. Identify your application's SAML **SP Entity ID** and **SP ACS URL**:

* **SP Entity ID**: The unique identifier for your Service Provider (usually a URI).
* **SP ACS URL**: The endpoint in your application to process SAML assertions from the IdP (e.g.,
  `[https://yourdomain.com/saml/acs](https://yourdomain.com/saml/acs)`).


2. Configure these values in your IdP's settings to establish a trust relationship with your application:

* Add a new **SAML Application** in the IdP portal.
* Provide the **SP Entity ID** and **SP ACS URL** as required.

---

## Step 3: Configure Environment Variables

In your application's `.env` file (or secret management service), define the following SAML variables:

```dotenv
#######################################
# SAML CONFIGURATION
#######################################
SAML_IDP_ENTITY_ID=https://wifi.tetrapi.pt/saml/metadata
SAML_IDP_SSO_URL=https://idp.tetrapi.pt/realms/Creative/protocol/saml
SAML_IDP_X509_CERT=MIICnzCCAYcCBgGEhbLwVjANBgkqhkiG9w0BAQsFADATMREwDwYDVQQDDAhDcmVhdGl2ZTAeFw0yMjExMTcxMzA1MDdaFw0zMjExMTcxMzA2NDdaMBMxETAPBgNVBAMMCENyZWF0aXZlMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAl8UQA9UfEbpVvyx4A9On9vBepnw+esG5ngNhPNPBVr+GymQnClgqdQhM660xgAcVpoJkpMW2KYXXVPBxtTLbd5tNNjijKUGq7MnNTqWZ0vYD02FQaRMtt3IJ6Z7EIw6Uf92ShA0XqNuHES4UJ7AtNms+038l5kM1pyeHgswDzSHTZAJ9KJ5jzT0+7SWKq8+KEHww0DiIlkgn14GuUJ3RA1yNuLsl8AipjJSUbGY/JlpV4pfU6b1JaXQZLQbWh4G/+GeyjpxrCXpYKg+fyan4FK1fRGK6XOt3pmXWKgzfxok3rVAdnrJsmzGhDvJnH+pzXz7T75ipGLx/m4sD8JAiUQIDAQABMA0GCSqGSIb3DQEBCwUAA4IBAQAERFypSeGRojvdr+Kp/hn/IEep6NQNFJHJWdCzKoypiJb0NdsiChieGTtHQZkma8vgPgZ/qKcaJUmF4yqg8FJOTu7UUWswlZNziv9ufVItG63wWtsrKn+XTgEV7629i+NWn6hCiva+aMGBmklQG0m162cOgtK5vAZs+0cZDBoGIFIn+Rz+dVPJXMBrQcrFPU4flX5ucymoMsQ1rWPF8dxw+rZ2M9ZzdxkYcxFBQEDumGebUtmCGVUis43L0Ma992xkgzhLeYpsQ5GHORi5OiB0HuaB/MqOsV6NTrSg5BjOMd6MBsAILOVRHnG4h3/TbUaKIjMM/CMVOYJgB3JNbtIx

SAML_SP_ENTITY_ID=https://wifi.tetrapi.pt/saml/metadata
SAML_SP_ACS_URL=http://localhost/saml/acs

SAML_IDENTIFIER_ATTRIBUTE=samlUuid
SAML_ATTRIBUTE_MAPPING='{"uuid":"samlUuid","email":"email","first_name":"givenName","last_name":"surname","username":"sAMAccountName"}'
SAML_DEBUG_DUMP=false

```

### Variable Reference

* **SAML_IDP_ENTITY_ID**: Unique URI identifier for the Identity Provider (
  `[https://wifi.tetrapi.pt/saml/metadata](https://wifi.tetrapi.pt/saml/metadata)`).
* **SAML_IDP_SSO_URL**: IdP Single Sign-On endpoint URL handling authentication requests.
* **SAML_IDP_X509_CERT**: Base64-encoded X.509 certificate provided by the IdP to verify SAML response signatures.
* **SAML_SP_ENTITY_ID**: Unique URI identifier for your Service Provider application.
* **SAML_SP_ACS_URL**: Assertion Consumer Service endpoint handling incoming SAML responses.
* **SAML_IDENTIFIER_ATTRIBUTE**: Attribute key that designates the primary user identifier (`samlUuid`).
* **SAML_ATTRIBUTE_MAPPING**: JSON string mapping application user properties to SAML response attributes:
* `uuid`: IdP attribute key used to match or create local user records (`samlUuid`).
* `email`: SAML attribute key for user email (`email`).
* `first_name`: SAML attribute key for first name (`givenName`).
* `last_name`: SAML attribute key for surname (`surname`).
* `username`: SAML attribute key mapped to external provider identity (`sAMAccountName`).


* **SAML_DEBUG_DUMP**: Set to `true` to enable verbose debug logging for SAML payloads during development.

---

## Step 4: Attribute Mapping & Fallback Behavior

The application's `CustomSamlUserFactory` processes incoming SAML assertions using the following resolution sequence:

1. **UUID Resolution**:

* Locates the identifier via the attribute mapped in `uuid` (`samlUuid`).
* *XML Fallback*: If the key is absent in the parsed attributes (e.g., when `use_attribute_friendly_name` is enabled
  globally but the IdP sends standard `Name` attributes), the application parses the raw `SAMLResponse` XML directly to
  extract standard `saml:Attribute` elements.


2. **User Account Resolution**:

* Searches for existing users matching `uuid`.
* Disabled accounts trigger an immediate session error flash and redirect to the landing page.
* Non-existent accounts are auto-provisioned with `isVerified = true`.


3. **Username / Provider ID Resolution**:

* Evaluates the attribute mapped in `username` (`sAMAccountName`).
* *Fallback 1*: Checks for an explicit `sAMAccountName` key in assertion attributes.
* *Fallback 2*: Falls back to the user's `email` address if no account name attribute exists in the assertion (e.g.,
  Google Workspace integrations).

---

## Step 5: Test the SAML Integration

1. Confirm that `SAML_SP_ENTITY_ID` and `SAML_SP_ACS_URL` in your `.env` match the metadata configured in your IdP
   portal.
2. Verify that the attribute names sent by the IdP match the JSON keys defined in `SAML_ATTRIBUTE_MAPPING`.
3. Test authentication using an active IdP account and verify auto-provisioning in your database.
4. If issues occur during login, set `SAML_DEBUG_DUMP=true` to inspect SAML payload details.
