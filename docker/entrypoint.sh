#!/bin/sh
set -e

echo "=============================================="
echo "  Product Catalog - Container Startup"
echo "=============================================="

# Create required directories
echo "Creating directories..."
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/api-docs
mkdir -p /var/log/supervisor
mkdir -p /var/run/supervisor
mkdir -p /var/run/nginx

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Verify Nginx configuration
echo "Verifying Nginx configuration..."
nginx -t || {
    echo "ERROR: Nginx configuration is invalid"
    exit 1
}

# Clear all caches first to avoid stale config issues
echo "Clearing caches..."
php /var/www/html/artisan config:clear 2>&1 || true
php /var/www/html/artisan route:clear 2>&1 || true
php /var/www/html/artisan view:clear 2>&1 || true
php /var/www/html/artisan cache:clear 2>&1 || true

# Test Laravel is working
echo "Testing Laravel..."
php /var/www/html/artisan --version || {
    echo "ERROR: Laravel is not working"
    php /var/www/html/artisan 2>&1 || true
    exit 1
}

# Wait for database to be ready (max 60 seconds)
echo "Waiting for database connection..."
MAX_RETRIES=30
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if php /var/www/html/artisan tinker --execute="try { DB::connection()->getPdo(); echo 'OK'; } catch(Exception \$e) { exit(1); }" 2>/dev/null | grep -q "OK"; then
        echo "Database connection established!"
        break
    fi
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Database not ready, retrying in 2 seconds... ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "WARNING: Could not verify database connection, continuing anyway..."
fi

# Run migrations
echo "Running database migrations..."
php /var/www/html/artisan migrate --force --no-interaction 2>&1 || echo "Migrations completed or skipped"

# Create storage link if not exists
if [ ! -L /var/www/html/public/storage ]; then
    echo "Creating storage link..."
    php /var/www/html/artisan storage:link 2>&1 || true
fi

# Optimize for production
echo "Optimizing application..."
php /var/www/html/artisan config:cache 2>&1 || {
    echo "WARNING: Config cache failed, running without cache"
}
php /var/www/html/artisan route:cache 2>&1 || {
    echo "WARNING: Route cache failed, running without cache"
}
php /var/www/html/artisan view:cache 2>&1 || true

echo "=============================================="
echo "  Startup complete - Starting services"
echo "=============================================="

# Execute the main command
exec "$@"
