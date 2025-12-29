# =============================================================================
# PRODUCT CATALOG - Production Dockerfile
# Optimized for Railway deployment
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Composer dependencies
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-builder

WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# -----------------------------------------------------------------------------
# Stage 2: Node.js build for frontend assets + runtime scripts
# -----------------------------------------------------------------------------
FROM node:20-alpine AS node-builder

WORKDIR /app

# Copy package files
COPY package.json package-lock.json ./

# Install ALL dependencies (including optional for xatlas-three)
# Use --ignore-scripts to avoid build issues
RUN npm ci || npm install

# Copy source files needed for build
COPY resources ./resources
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY --from=composer-builder /app/vendor ./vendor

# Build frontend assets
RUN npm run build

# Copy runtime scripts
COPY scripts ./scripts

# -----------------------------------------------------------------------------
# Stage 3: Production image
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-alpine

# Install system dependencies + Node.js for runtime scripts (UV processing, Draco compression)
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    postgresql-dev \
    icu-dev \
    linux-headers \
    nodejs \
    npm \
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        mbstring \
        xml \
        bcmath \
        intl \
        opcache \
        pcntl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Clean up build dependencies
RUN apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /var/cache/apk/* /tmp/*

# PHP configuration for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy custom PHP configuration
COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/99-custom.ini"
COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy init script
COPY docker/scripts/init.sh /var/www/html/docker/scripts/init.sh
RUN chmod +x /var/www/html/docker/scripts/init.sh

# Set working directory
WORKDIR /var/www/html

# Copy application from builder stages
COPY --from=composer-builder /app .
COPY --from=node-builder /app/public/build ./public/build

# Copy Node.js dependencies and scripts for runtime (UV processing, Draco compression)
COPY --from=node-builder /app/node_modules ./node_modules
COPY --from=node-builder /app/scripts ./scripts

# Create required directories
RUN mkdir -p storage/logs \
    && mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/api-docs \
    && mkdir -p /var/run/nginx \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /var/run/supervisor

# Set permissions - use 777 for storage to ensure writability
# www-data user needs write access for logs, sessions, cache, views
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 777 storage \
    && chmod -R 777 bootstrap/cache

# Copy and set entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Fixed port for Railway
ENV PORT=8080
EXPOSE 8080

# Healthcheck endpoint
# Use /up.php for fast health checks (bypasses Laravel)
# Start period increased to allow Laravel initialization to complete
HEALTHCHECK --interval=30s --timeout=10s --start-period=90s --retries=3 \
    CMD curl -f http://localhost:8080/up.php || exit 1

# Start supervisor
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
