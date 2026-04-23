ENV_FILE="/var/www/openroaming/.env"

JWT_PASSPHRASE="$1"
TRUSTED_PROXIES="$2"
TURNSTILE_KEY="$3"
TURNSTILE_SECRET="$4"

# Check if you received arguments
if [ -z "$TRUSTED_PROXIES" ] || [ -z "$TURNSTILE_KEY" ] || [ -z "$TURNSTILE_SECRET" ]; then
    echo "Use: $0 [JWT_PASSPHRASE] \"TRUSTED_PROXIES\" \"TURNSTILE_KEY\" \"TURNSTILE_SECRET\""
    echo "Note: JWT_PASSPHRASE is optional."
    exit 1
fi

# Check if .env exists
if [ ! -f "$ENV_FILE" ]; then
    echo "Error: The file $ENV_FILE doesn't exist."
    exit 1
fi

set_env() {
    KEY="$1"
    VALUE="$2"

    # remove old setting
    sed -i "/^$KEY=/d" "$ENV_FILE"

    # add new value
    echo "$KEY=\"$VALUE\"" >> "$ENV_FILE"

    echo "Updated: $KEY"
}

# update $JWT_PASSPHRASE if its necessary
if [ -n "$JWT_PASSPHRASE" ]; then
    set_env "JWT_PASSPHRASE" "$JWT_PASSPHRASE"
else
    echo "JWT_PASSPHRASE not given"
fi

# update env values
set_env "TRUSTED_PROXIES" "$TRUSTED_PROXIES"
set_env "TURNSTILE_KEY" "$TURNSTILE_KEY"
set_env "TURNSTILE_SECRET" "$TURNSTILE_SECRET"

echo "Update completed successfully."