#!/bin/sh
#

# Check if required files exist
if [ ! -f ../signing-keys/cert.pem ] || [ ! -f ../signing-keys/ca.pem ] || [ ! -f ../signing-keys/privkey.pem ]; then
  echo "Error: Required files (cert.pem, ca.pem, or privkey.pem) not found in ../signing-keys/"
  exit 1
fi

# Create the PFX file
cat ../signing-keys/cert.pem ../signing-keys/ca.pem \
 | openssl pkcs12 -export \
     -inkey ../signing-keys/privkey.pem -password "pass:openroaming" -out ../signing-keys/windowsKey.pfx

# Check if the PFX file was created successfully
if [ ! -f ../signing-keys/windowsKey.pfx ]; then
  echo "Error: Failed to create the PFX file (windowsKey.pfx)"
  exit 1
fi

# Set permissions and ownership
chmod 640 ../signing-keys/windowsKey.pfx
chown www-data:www-data ../signing-keys/windowsKey.pfx
