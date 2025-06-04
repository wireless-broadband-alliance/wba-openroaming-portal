#!/bin/bash
# Script to generate self-signed SSL certificate

# Create directory if it doesn't exist
mkdir -p self-signed-ssl

# Generate self-signed certificate valid for 365 days
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout self-signed-ssl/ssl.key \
  -out self-signed-ssl/ssl.crt \
  -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost" \
  -addext "subjectAltName = DNS:localhost,DNS:127.0.0.1"

# Set proper permissions
chmod 600 self-signed-ssl/ssl.key
chmod 644 self-signed-ssl/ssl.crt

echo "Self-signed SSL certificate created successfully in the self-signed-ssl directory."
echo "Run 'docker-compose -f docker-compose-ssl.yml up' to start with SSL support." 