# Official PHP image with Apache (pinned PHP version)
FROM php:8.5.12-apache

# Install system dependencies and common PHP extensions (adjust as needed).
# OPcache is built into PHP 8.5+, so it is not installed here.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev libicu-dev libonig-dev libpng-dev libjpeg-dev libfreetype6-dev libxml2-dev git unzip curl git \
        libchromaprint-tools \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli intl zip gd \
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
    git clone --depth=1 --branch main --recurse-submodules https://github.com/Darknetzz/mp3-web.git /var/www/html && \
    chown -R www-data:www-data /var/www/html

# Install composer (global)
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer --version
RUN composer install -d /var/www/html --no-dev --optimize-autoloader

# Set working directory
WORKDIR /var/www/html

# Accounts (data/) and playlists live here: mount volumes to keep them across containers.
# Optional env: AUTH_REQUIRED, ADMIN_USERNAME, ADMIN_PASSWORD, MUSIC_FOLDERS, SITE_TITLE, SETUP_TOKEN (see README).
RUN mkdir -p /var/www/html/data /var/www/html/playlists && chown www-data:www-data /var/www/html/data /var/www/html/playlists
VOLUME ["/var/www/html/data", "/var/www/html/playlists"]

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost/ || exit 1

# Default command (from base image)
CMD ["apache2-foreground"]