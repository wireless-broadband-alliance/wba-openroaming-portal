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

# backup (only if necessary!)
#BACKUP_FILE="${ENV_FILE}.backup_$(date +%F_%H-%M-%S)"
#cp "$ENV_FILE" "$BACKUP_FILE"
#echo "Backup created on: $BACKUP_FILE"

set_env() {
    KEY="$1"
    VALUE="$2"

    if grep -q "^$KEY=" "$ENV_FILE"; then
        sed -i "s|^$KEY=.*|$KEY=\"$VALUE\"|" "$ENV_FILE"
        echo "Updated: $KEY"
    else
        echo "$KEY=\"$VALUE\"" >> "$ENV_FILE"
        echo "Added: $KEY"
    fi
}

# update env values
set_env "DATABASE_URL" "$DATABASE_URL"
set_env "DATABASE_FREERADIUS_URL" "$DATABASE_FREERADIUS_URL"

echo "Update completed successfully."