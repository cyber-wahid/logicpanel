FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libpq-dev \
    libicu-dev \
    curl \
    mariadb-client \
    autoconf \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# Install PHP Extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli pdo_pgsql pgsql zip mbstring intl

# Install PECL extensions (Redis)
RUN pecl install redis && docker-php-ext-enable redis

# Enable Apache Modules (no SSL needed - Traefik handles it)
RUN a2enmod rewrite proxy proxy_http proxy_wstunnel

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Docker CLI (Needed for managing user apps)
COPY --from=docker:latest /usr/local/bin/docker /usr/local/bin/docker

# Set working directory
WORKDIR /var/www/html

# Copy application files (respecting .dockerignore)
COPY . .

# Install dependencies via Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configuration for Apache - Simple HTTP only (Traefik terminates SSL)
ENV APACHE_DOCUMENT_ROOT="/var/www/html"
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf
RUN sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Apache listens on port 80 only (Traefik handles 777/999 externally)
RUN echo "Listen 80" > /etc/apache2/ports.conf

# Custom PHP Config
RUN echo "upload_max_filesize = 512M\npost_max_size = 512M\nmemory_limit = 512M\nmax_execution_time = 300" > /usr/local/etc/php/conf.d/logicpanel.ini

# Fix permissions and ensure directories exist
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views storage/user-apps \
    && mkdir -p src/storage/framework/ratelimit docker/traefik/apps \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/src/storage \
    && chmod -R 775 /var/www/html/docker/traefik/apps \
    && find /var/www/html/storage -type d -exec chmod 775 {} + \
    && find /var/www/html/storage -type f -exec chmod 664 {} +

# Give www-data sudo access for rm and chown commands (for managing user apps)
RUN apt-get update && apt-get install -y sudo \
    && echo "www-data ALL=(ALL) NOPASSWD: /bin/rm, /usr/bin/chown" >> /etc/sudoers \
    && rm -rf /var/lib/apt/lists/*

# Setup entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Security: Reduce privileges where possible
# Apache runs as www-data by default, which is appropriate

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

# Only expose port 80 internally - Traefik handles external SSL ports
EXPOSE 80
