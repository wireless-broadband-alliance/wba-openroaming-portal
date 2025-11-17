# WBA CA Bundle Instructions

This folder contains the individual WBA certificates used to create the CA bundle file `ca-bundle-wba.pem`.

## Rebuilding the CA Bundle

If the `ca-bundle-wba.pem` file is missing or needs to be recreated, you can rebuild it using the following command:

```bash
cat WBA_Issuing_CA.pem WBA_Issuing7_CA.pem WBA_Policy7_CA.pem WBA_Cisco_Policy_CA.pem WBA_OpenRoaming_Root.pem > ca-bundle-wba.pem
````

### Notes

* The order of certificates in the bundle is important:

    1. Intermediate CAs first (`WBA_Issuing_CA.pem`, `WBA_Issuing7_CA.pem`, `WBA_Policy7_CA.pem`,
       `WBA_Cisco_Policy_CA.pem`)
    2. Root CA last (`WBA_OpenRoaming_Root.pem`)
* After creating the bundle, you can verify it with:

```bash
openssl crl2pkcs7 -nocrl -certfile ca-bundle-wba.pem | openssl pkcs7 -print_certs -noout
```

* This file is used by PHP, OpenSSL, and other tools to verify TLS connections against the WBA certificate chain.

## Where to get the certificates

If any of the certificates are missing, you can download them from the official WBA OpenRoaming Connector repository:

[WBA OpenRoaming Connector - RadSec Proxy Certs](https://github.com/wireless-broadband-alliance/wba-openroaming-connector/tree/master/hybrid/configs/radsecproxy/certs/chain)


