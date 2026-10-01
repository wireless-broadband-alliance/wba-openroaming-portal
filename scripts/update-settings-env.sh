#!/bin/bash

# Dynamically resolves the .env path based on the script's directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../.env"

if [ "$#" -eq 3 ]; then
    JWT_PASSPHRASE=""
    TRUSTED_PROXIES="$1"
    TURNSTILE_KEY="$2"
    TURNSTILE_SECRET="$3"
elif [ "$#" -eq 4 ]; then
    JWT_PASSPHRASE="$1"
    TRUSTED_PROXIES="$2"
    TURNSTILE_KEY="$3"
    TURNSTILE_SECRET="$4"
else
    echo "Error: Incorrect number of arguments."
    echo "Usage: $0 [JWT_PASSPHRASE] \"TRUSTED_PROXIES\" \"TURNSTILE_KEY\" \"TURNSTILE_SECRET\""
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    echo "Error: The file $ENV_FILE does not exist."
    exit 1
fi

set_env() {
    local KEY="$1"
    local VALUE="$2"
    local TMP_FILE="${ENV_FILE}.tmp"

    if grep -q "^$KEY=" "$ENV_FILE"; then
        sed "s|^$KEY=.*|$KEY=\"$VALUE\"|" "$ENV_FILE" > "$TMP_FILE"
        cat "$TMP_FILE" > "$ENV_FILE"
        rm -f "$TMP_FILE"
    else
        echo "$KEY=\"$VALUE\"" >> "$ENV_FILE"
    fi

    echo "Updated: $KEY"
}

if [ "$#" -eq 4 ]; then
    set_env "JWT_PASSPHRASE" "$JWT_PASSPHRASE"
else
    echo "JWT_PASSPHRASE omitted (unchanged)"
fi

set_env "TRUSTED_PROXIES" "$TRUSTED_PROXIES"
set_env "TURNSTILE_KEY" "$TURNSTILE_KEY"
set_env "TURNSTILE_SECRET" "$TURNSTILE_SECRET"

echo "Update completed successfully."
