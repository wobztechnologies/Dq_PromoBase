#!/bin/sh

echo "Starting Laravel initialization..."

# Fix permissions first (fast)
chmod -R 777 /var/www/html/storage 2>&1
chmod -R 777 /var/www/html/bootstrap/cache 2>&1

# Run migrations (important)
php /var/www/html/artisan migrate --force --no-interaction 2>&1

# Create storage link
php /var/www/html/artisan storage:link 2>&1 || echo "Storage link already exists"

# Clear and rebuild caches
php /var/www/html/artisan config:cache 2>&1
php /var/www/html/artisan route:cache 2>&1
php /var/www/html/artisan view:cache 2>&1

echo "Laravel initialization complete"

