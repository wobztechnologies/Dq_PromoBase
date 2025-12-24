#!/bin/sh

echo "Starting Laravel initialization..."

# Clear caches first
php /var/www/html/artisan config:clear 2>&1
php /var/www/html/artisan route:clear 2>&1
php /var/www/html/artisan view:clear 2>&1

# Run migrations
php /var/www/html/artisan migrate --force --no-interaction 2>&1

# Fix permissions
chmod -R 777 /var/www/html/storage 2>&1
chown -R www-data:www-data /var/www/html/storage 2>&1
chmod -R 777 /var/www/html/bootstrap/cache 2>&1
chown -R www-data:www-data /var/www/html/bootstrap/cache 2>&1

# Rebuild caches
php /var/www/html/artisan config:cache 2>&1
php /var/www/html/artisan route:cache 2>&1

# Create storage link
php /var/www/html/artisan storage:link 2>&1 || echo "Storage link already exists"

# Warmup: wait for nginx/php-fpm to be ready
echo "Warming up application..."
sleep 3

# Warmup PHP file (fast)
WARMUP_PHP=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 http://127.0.0.1:8080/up.php 2>/dev/null)
echo "Warmup PHP: HTTP $WARMUP_PHP"

# Warmup Laravel (may be slow on first request)
WARMUP_LARAVEL=$(curl -s -o /dev/null -w "%{http_code}" --max-time 180 http://127.0.0.1:8080/up 2>/dev/null)
echo "Warmup Laravel: HTTP $WARMUP_LARAVEL"

# Additional warmup for API routes
curl -s -o /dev/null --max-time 60 http://127.0.0.1:8080/api/ext/categories 2>/dev/null
echo "Warmup API complete"

echo "Laravel initialization complete"

