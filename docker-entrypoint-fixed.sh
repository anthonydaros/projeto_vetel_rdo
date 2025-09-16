#!/bin/bash
set -e

# Wait for MySQL to be ready (with SSL disabled)
echo "Waiting for MySQL to be ready..."
until mysqladmin ping -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-root}" -p"${DB_PASSWORD:-root}" --silent 2>/dev/null; do
    echo "MySQL is not ready yet. Waiting..."
    sleep 2
done
echo "MySQL is ready!"

# Handle photo storage directory and volume initialization
PHOTO_DIR="/var/www/html/img/album"
PHOTO_BACKUP="/var/www/html/img/album_backup"

# Create directory if it doesn't exist
if [ ! -d "$PHOTO_DIR" ]; then
    echo "Creating uploads directory..."
    mkdir -p "$PHOTO_DIR"
fi

# Set proper permissions
chown -R www-data:www-data "$PHOTO_DIR"
chmod -R 755 "$PHOTO_DIR"

# Make sure .env exists
if [ ! -f "/var/www/html/.env" ]; then
    echo "Creating .env file..."
    cat > /var/www/html/.env << EOF
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=vetel_db
DB_USERNAME=root
DB_PASSWORD=root
APP_ENV=development
APP_DEBUG=true
TIMEZONE=America/Sao_Paulo
PHOTO_STORAGE_PATH=img/album
EOF
fi

echo "Starting Apache..."
exec apache2-foreground