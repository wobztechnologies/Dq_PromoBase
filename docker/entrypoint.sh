#!/bin/sh
set -e

# Create required directories
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/api-docs
mkdir -p /var/log/supervisor
mkdir -p /var/run/supervisor
mkdir -p /var/run/nginx

# Set permissions
chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true
chown -R www-data:www-data /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage 2>/dev/null || true
chmod -R 775 /var/www/html/bootstrap/cache 2>/dev/null || true

# Validate Nginx configuration
nginx -t || {
    echo "ERROR: Nginx configuration is invalid"
    exit 1
}

# Execute the main command (supervisord)
exec "$@"
