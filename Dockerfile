# Use official PHP (latest stable) with Apache (includes latest Apache 2.x)
FROM php:8.3-apache

# Install system dependencies and common PHP extensions (adjust as needed)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev libicu-dev libonig-dev libpng-dev libjpeg-dev libfreetype6-dev libxml2-dev git unzip curl git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli intl zip gd opcache \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

# Set recommended PHP settings (tune as necessary)
RUN { \
        echo "memory_limit=256M"; \
        echo "upload_max_filesize=50M"; \
        echo "post_max_size=50M"; \
        echo "file_uploads = On"; \
        echo "max_execution_time=300"; \
        echo "date.timezone=UTC"; \
        echo "opcache.enable=1"; \
        echo "opcache.validate_timestamps=1"; \
        echo "opcache.max_accelerated_files=20000"; \
    } > /usr/local/etc/php/conf.d/custom.ini

RUN set -eux; \
    rm -rf /var/www/html/*; \
    git clone --depth=1 https://github.com/Darknetzz/mp3-web.git /var/www/html && \
    cd /var/www/html && \
    git checkout dev && \
    git pull --recurse-submodules; \
    chown -R www-data:www-data /var/www/html

# Install composer (global)
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer --version
RUN composer install -d /var/www/html --no-dev --optimize-autoloader

# Set working directory
WORKDIR /var/www/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost/ || exit 1

# Default command (from base image)
CMD ["apache2-foreground"]