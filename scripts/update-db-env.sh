#!/bin/bash

ENV_FILE="/var/www/openroaming/.env"

DATABASE_URL="$1"
DATABASE_FREERADIUS_URL="$2"

# Check if you received arguments
if [ -z "$DATABASE_URL" ] || [ -z "$DATABASE_FREERADIUS_URL" ]; then
    echo "Use: $0 \"DATABASE_URL\" \"DATABASE_FREERADIUS_URL\""
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

    TMP_FILE="${ENV_FILE}.tmp"

    # Remove old key into temp file
    sed "/^$KEY=/d" "$ENV_FILE" > "$TMP_FILE"

    # Append new key
    echo "$KEY=\"$VALUE\"" >> "$TMP_FILE"

    # Overwrite original .env content without breaking mount
    cat "$TMP_FILE" > "$ENV_FILE"
    rm -f "$TMP_FILE"

    echo "Updated: $KEY"
}

# update env values
set_env "DATABASE_URL" "$DATABASE_URL"
set_env "DATABASE_FREERADIUS_URL" "$DATABASE_FREERADIUS_URL"

echo "Update completed successfully."
