#!/bin/sh
#

# Check if required files exist
if [ ! -f ../signing-keys/cert.pem ] || [ ! -f ../signing-keys/ca/ca.pem ] || [ ! -f ../signing-keys/privkey.pem ]; then
  echo "Error: Required files (cert.pem, ca.pem, or privkey.pem) not found in ../signing-keys/"
  exit 1
fi

# Fix potential Windows line endings
sed -i 's/\r//' ../signing-keys/cert.pem
sed -i 's/\r//' ../signing-keys/ca/ca.pem
sed -i 's/\r//' ../signing-keys/privkey.pem

# Fix missing newlines at end of files
sed -i -e '$a\' ../signing-keys/cert.pem
sed -i -e '$a\' ../signing-keys/ca/ca.pem
sed -i -e '$a\' ../signing-keys/privkey.pem

# Validate PEM files are readable
if ! openssl x509 -in ../signing-keys/cert.pem -noout 2>/dev/null; then
  echo "Error: cert.pem is not a valid certificate"
  exit 1
fi

if ! openssl x509 -in ../signing-keys/ca/ca.pem -noout 2>/dev/null; then
  echo "Error: ca/ca.pem is not a valid certificate"
  exit 1
fi

if ! openssl pkey -in ../signing-keys/privkey.pem -noout 2>/dev/null; then
  echo "Error: privkey.pem is not a valid private key"
  exit 1
fi

# Create the PFX file
openssl pkcs12 -export \
  -in ../signing-keys/cert.pem \
  -certfile ../signing-keys/ca/ca.pem \
  -inkey ../signing-keys/privkey.pem \
  -password "pass:" \
  -keypbe AES-256-CBC \
  -certpbe AES-256-CBC \
  -macalg SHA256 \
  -out ../signing-keys/windowsKey.pfx

# Check if the PFX file was created successfully
if [ ! -f ../signing-keys/windowsKey.pfx ]; then
  echo "Error: Failed to create the PFX file (windowsKey.pfx)"
  exit 1
fi

# Set permissions and ownership
chmod 640 ../signing-keys/windowsKey.pfx
chown www-data:www-data ../signing-keys/windowsKey.pfx

echo "Success: windowsKey.pfx created successfully"
