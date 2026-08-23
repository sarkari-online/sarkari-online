FROM php:8.3-fpm-alpine

# Install system dependencies & libraries for PHP extensions
RUN apk add --no-cache \
    nginx \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    bash \
    dcron \
    supervisor \
    mariadb-client

# Configure & Install PHP extensions required for Sarkari.online
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        gd \
        xml \
        zip \
        opcache

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html

# Copy Docker configuration files
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/cron/crontab /etc/crontabs/root

# Setup storage and uploads permissions
RUN chmod 0644 /etc/crontabs/root \
    && mkdir -p /var/www/html/storage/logs /var/www/html/storage/cache /var/www/html/storage/generated /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/uploads \
    && chmod -R 775 /var/www/html/storage /var/www/html/uploads \
    && chmod +x /var/www/html/docker/entrypoint.sh

# Expose port for internal Nginx
EXPOSE 80

# Run entrypoint script
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
