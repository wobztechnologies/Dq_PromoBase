#!/bin/sh
set -e

echo "=============================================="
echo "  Product Catalog - Container Startup"
echo "=============================================="

# Verify Nginx configuration
echo "Verifying Nginx configuration..."
nginx -t || {
    echo "ERROR: Nginx configuration is invalid"
    exit 1
}

# Create required directories
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/log/supervisor
mkdir -p /var/run/supervisor
mkdir -p /var/run/nginx

# Ensure supervisor directories exist and have correct permissions
mkdir -p /var/log/supervisor /var/run/supervisor
chmod 755 /var/log/supervisor
chmod 755 /var/run/supervisor

# Set permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Wait for database to be ready
echo "Waiting for database connection..."
MAX_RETRIES=30
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if php /var/www/html/artisan tinker --execute="try { DB::connection()->getPdo(); echo 'connected'; } catch(Exception \$e) { exit(1); }" 2>/dev/null | grep -q "connected"; then
        echo "Database connection established!"
        break
    fi
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Database not ready, retrying in 2 seconds... ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "ERROR: Could not connect to database after $MAX_RETRIES attempts"
    exit 1
fi

# Run migrations (with --force for production)
echo "Running database migrations..."
php /var/www/html/artisan migrate --force

# Publish Swagger assets BEFORE caching routes (so routes are registered)
echo "Publishing Swagger assets..."
php /var/www/html/artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider" --tag="l5-swagger-assets" --force 2>/dev/null || {
    echo "Swagger assets publish failed, copying manually..."
    # Fallback: copy assets manually if publish command fails
    if [ -d "/var/www/html/vendor/swagger-api/swagger-ui/dist" ]; then
        mkdir -p /var/www/html/public/vendor/swagger-api/swagger-ui/dist
        cp -r /var/www/html/vendor/swagger-api/swagger-ui/dist/* /var/www/html/public/vendor/swagger-api/swagger-ui/dist/ 2>/dev/null || true
        chown -R www-data:www-data /var/www/html/public/vendor 2>/dev/null || true
    fi
}

# Generate Swagger documentation
echo "Generating API documentation..."
php /var/www/html/artisan l5-swagger:generate 2>/dev/null || echo "Swagger generation skipped"

# Clear and optimize caches (after Swagger is set up)
echo "Optimizing application..."
php /var/www/html/artisan config:cache
php /var/www/html/artisan route:cache
php /var/www/html/artisan view:cache
php /var/www/html/artisan event:cache
php /var/www/html/artisan filament:cache-components

# Create storage link if not exists
if [ ! -L /var/www/html/public/storage ]; then
    echo "Creating storage link..."
    php /var/www/html/artisan storage:link
fi

echo "=============================================="
echo "  Startup complete - Starting services"
echo "=============================================="

# Execute the main command
exec "$@"
