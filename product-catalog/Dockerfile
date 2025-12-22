# =============================================================================
# PRODUCT CATALOG - Production Dockerfile
# Optimized for EasyPanel deployment
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
# Stage 2: Node.js build for frontend assets
# -----------------------------------------------------------------------------
FROM node:20-alpine AS node-builder

WORKDIR /app

# Copy package files
COPY package.json package-lock.json ./

# Install dependencies
RUN npm ci

# Copy source files needed for build
COPY resources ./resources
COPY vite.config.js postcss.config.js ./
COPY --from=composer-builder /app/vendor ./vendor

# Build frontend assets
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 3: Production image
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    gettext \
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
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
    --with-jpeg \
    --with-webp \
    --with-freetype

RUN docker-php-ext-install \
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

# Install Swoole for Laravel Octane
RUN pecl install swoole && \
    docker-php-ext-enable swoole

# Install Redis extension
RUN pecl install redis && \
    docker-php-ext-enable redis

# Clean up
RUN apk del $PHPIZE_DEPS linux-headers && \
    rm -rf /var/cache/apk/* /tmp/*

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

# Set working directory
WORKDIR /var/www/html

# Copy application from builder stages
COPY --from=composer-builder /app .
COPY --from=node-builder /app/public/build ./public/build

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create required directories
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/run/nginx \
    && chown -R www-data:www-data /var/www/html/storage

# Copy and set entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Railway uses PORT environment variable
ENV PORT=80
EXPOSE ${PORT}

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:${PORT}/health || exit 1

# Start supervisor
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
