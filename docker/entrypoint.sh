#!/bin/bash
set -e

echo "=== LogicPanel Container Starting ==="

# Auto-generate JWT_SECRET if not provided
if [ -z "$JWT_SECRET" ]; then
    export JWT_SECRET=$(head -c 32 /dev/urandom | base64 | tr -d '=+/' | head -c 48)
    echo "⚠ JWT_SECRET not set, generated random secret for this session"
    echo "  For persistent sessions across restarts, add JWT_SECRET to your .env file"
fi

# Fix docker socket permissions
if [ -S /var/run/docker.sock ]; then
    chmod 666 /var/run/docker.sock
fi

# Fix user-apps volume permissions (allow www-data to write)
if [ -d /var/www/html/storage/user-apps ]; then
    chown www-data:www-data /var/www/html/storage/user-apps
    chmod 775 /var/www/html/storage/user-apps
fi

# Install Composer dependencies (volume mount may override build-time vendor folder)
echo "=== Installing Composer Dependencies ==="
if [ -f /var/www/html/composer.json ]; then
    cd /var/www/html
    if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
        echo "Vendor folder missing, running composer install..."
        composer install --no-dev --optimize-autoloader --no-interaction
    else
        echo "Vendor folder exists, skipping composer install."
    fi
else
    echo "No composer.json found, skipping dependency installation."
fi

# Configure WebSocket Proxy for Terminal Gateway
echo "=== Configuring WebSocket Proxy ==="
cat >> /etc/apache2/sites-enabled/000-default.conf << 'WSEOF'

# WebSocket Proxy for Terminal Gateway
<Location /ws/terminal>
    ProxyPass ws://logicpanel_gateway:3002
    ProxyPassReverse ws://logicpanel_gateway:3002
    ProxyPreserveHost On
</Location>
WSEOF
echo "✓ WebSocket proxy configured for /ws/terminal"

# Fix permissions for config and storage directories
echo "=== Fixing Permissions ==="
chown -R www-data:www-data /var/www/html/config/ 2>/dev/null || true
chmod 755 /var/www/html/config/ 2>/dev/null || true
chmod 644 /var/www/html/config/*.json 2>/dev/null || true
chown -R www-data:www-data /var/www/html/storage/ 2>/dev/null || true
chmod -R 775 /var/www/html/storage/ 2>/dev/null || true
# Fix src/storage permissions (rate limiter, cache, etc.)
if [ -d /var/www/html/src/storage ]; then
    chown -R www-data:www-data /var/www/html/src/storage/ 2>/dev/null || true
    chmod -R 775 /var/www/html/src/storage/ 2>/dev/null || true
    mkdir -p /var/www/html/src/storage/framework/ratelimit 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/src/storage/framework/ 2>/dev/null || true
fi
# Fix traefik/apps permissions (app creation writes route configs here)
if [ -d /var/www/html/docker/traefik/apps ]; then
    chown -R www-data:www-data /var/www/html/docker/traefik/apps/ 2>/dev/null || true
    chmod -R 775 /var/www/html/docker/traefik/apps/ 2>/dev/null || true
fi
echo "✓ Permissions fixed for config, storage, and traefik"

# Initialize settings.json from environment if needed
echo "=== Checking Settings ==="
SETTINGS_FILE="/var/www/html/config/settings.json"
if [ ! -f "$SETTINGS_FILE" ] || ! grep -q '"hostname"' "$SETTINGS_FILE" 2>/dev/null; then
    echo "Creating settings.json from environment..."
    mkdir -p /var/www/html/config
    cat > "$SETTINGS_FILE" << SETTINGSEOF
{
    "hostname": "${PANEL_DOMAIN:-${VIRTUAL_HOST:-localhost}}",
    "master_port": "${MASTER_PORT:-9999}",
    "user_port": "${USER_PORT:-7777}",
    "company_name": "LogicPanel",
    "contact_email": "${ADMIN_EMAIL:-admin@localhost}",
    "enable_ssl": "1",
    "letsencrypt_email": "${ADMIN_EMAIL:-admin@localhost}",
    "timezone": "UTC",
    "allow_registration": "1"
}
SETTINGSEOF
    chown www-data:www-data "$SETTINGS_FILE"
    echo "✓ Settings initialized from environment"
else
    echo "✓ Settings file exists"
fi

# Run database migrations
echo "=== Running Database Migrations ==="
if [ -f /var/www/html/docker/migrate.sh ]; then
    chmod +x /var/www/html/docker/migrate.sh
    /var/www/html/docker/migrate.sh || echo "⚠ Migration script had warnings (continuing...)"
else
    # Inline migration if script not found
    echo "Running inline database setup..."
    
    # Wait for database
    MAX_RETRIES=30
    RETRY_COUNT=0
    DB_HOST="${DB_HOST:-logicpanel-db}"
    DB_USER="${DB_USERNAME:-logicpanel}"
    DB_PASS="${DB_PASSWORD:-logicpanel_password}"
    DB_NAME="${DB_DATABASE:-logicpanel}"
    
    while ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" &>/dev/null; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            echo "✗ Database not ready, skipping migration"
            break
        fi
        echo "Waiting for database... ($RETRY_COUNT/$MAX_RETRIES)"
        sleep 2
    done
    
    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        # Apply schema
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --ssl-mode=REQUIRED "$DB_NAME" < /var/www/html/database/schema.sql 2>/dev/null || true

        # Apply all migration files
        for migration in /var/www/html/database/migrations/*.sql; do
            if [ -f "$migration" ]; then
                mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --ssl-mode=REQUIRED "$DB_NAME" < "$migration" 2>/dev/null || true
            fi
        done
        echo "✓ Database migration completed"
    fi
fi

echo "=== LogicPanel Ready ==="
echo "Note: SSL is handled by Traefik reverse proxy"

# Pass control to the main command (apache2-foreground)
exec "$@"
