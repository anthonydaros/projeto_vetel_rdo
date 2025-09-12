# Multi-stage build for PHP application
# Stage 1: Build dependencies
FROM composer:2.6 AS composer-build

WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# Copy application files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Stage 2: Production image
FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    curl \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        gd \
        zip \
        intl \
        opcache \
        exif \
        fileinfo \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers expires

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Custom PHP configuration
COPY <<EOF $PHP_INI_DIR/conf.d/custom.ini
; Performance
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1

; File uploads
upload_max_filesize=10M
post_max_size=100M
max_file_uploads=20

; Memory and execution
memory_limit=256M
max_execution_time=300
max_input_time=300

; Session
session.cookie_httponly=1
session.use_only_cookies=1
session.cookie_samesite=Lax
session.gc_maxlifetime=3600

; Security
expose_php=Off
display_errors=Off
display_startup_errors=Off
log_errors=On
error_log=/var/log/apache2/php_errors.log

; Timezone
date.timezone=America/Sao_Paulo
EOF

# Configure Apache
COPY <<EOF /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Security headers
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
    </Directory>
    
    # Redirect to HTTPS (Coolify handles SSL termination)
    RewriteEngine On
    RewriteCond %{HTTP:X-Forwarded-Proto} !https
    RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
    
    # Error pages
    ErrorDocument 404 /404.php
    ErrorDocument 500 /500.php
    
    # Logging
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Create necessary directories
RUN mkdir -p /var/www/html/img/album \
    && mkdir -p /var/www/html/relatorios \
    && mkdir -p /var/www/html/vendor \
    && mkdir -p /var/log/apache2 \
    && mkdir -p /var/www/sessions

# Set working directory
WORKDIR /var/www/html

# Copy application files from builder
COPY --from=composer-build /app/vendor ./vendor
COPY --from=composer-build /app/composer.json ./composer.json
COPY --from=composer-build /app/composer.lock* ./composer.lock

# Copy application code
COPY . .

# Create non-root user
RUN useradd -m -u 1000 -s /bin/bash appuser

# Set proper permissions
RUN chown -R appuser:appuser /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/img/album \
    && chmod -R 777 /var/www/html/relatorios \
    && chmod -R 777 /var/www/sessions \
    && chown -R appuser:appuser /var/log/apache2 \
    && chown -R appuser:appuser /var/run/apache2

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/health.php || exit 1

# Switch to non-root user
USER appuser

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]