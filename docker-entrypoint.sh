#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
until mysql -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USER:-root}" -p"${DB_PASSWORD:-root}" -e "SELECT 1" >/dev/null 2>&1; do
    echo "MySQL is not ready yet. Waiting..."
    sleep 2
done
echo "MySQL is ready!"

# Run SQL initialization script if database is empty
echo "Checking if database needs initialization..."
TABLE_COUNT=$(mysql -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USER:-root}" -p"${DB_PASSWORD:-root}" -D"${DB_NAME:-vetel_db}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME:-vetel_db}';" -s -N)

if [ "$TABLE_COUNT" -eq 0 ]; then
    echo "Database is empty. Running initialization script..."
    mysql -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USER:-root}" -p"${DB_PASSWORD:-root}" -D"${DB_NAME:-vetel_db}" < /var/www/html/sql/formulario_bd.sql
    echo "Database initialized successfully!"
else
    echo "Database already initialized (found $TABLE_COUNT tables)."
fi

# Handle photo storage directory and volume initialization
PHOTO_DIR="/var/www/html/img/album"
PHOTO_BACKUP="/var/www/html/img/album_backup"

# If this is a fresh volume (empty directory), copy existing photos from image
if [ -d "$PHOTO_DIR" ]; then
    FILE_COUNT=$(find "$PHOTO_DIR" -type f -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.webp" 2>/dev/null | wc -l)
    echo "Found $FILE_COUNT photos in volume"

    # If volume is empty but backup exists, restore photos
    if [ "$FILE_COUNT" -eq 0 ] && [ -d "$PHOTO_BACKUP" ]; then
        echo "Volume is empty. Restoring photos from image backup..."
        cp -Rpv "$PHOTO_BACKUP/"* "$PHOTO_DIR/" 2>/dev/null || true
        echo "Photos restored from backup"
    fi
else
    echo "Creating uploads directory..."
    mkdir -p "$PHOTO_DIR"

    # If backup exists, copy photos
    if [ -d "$PHOTO_BACKUP" ]; then
        echo "Copying initial photos from image..."
        cp -Rpv "$PHOTO_BACKUP/"* "$PHOTO_DIR/" 2>/dev/null || true
        echo "Initial photos copied"
    fi
fi

# Set proper ownership
chown -R www-data:www-data /var/www/html/img

# Create relatorios directory if it doesn't exist
if [ ! -d "/var/www/html/relatorios" ]; then
    echo "Creating reports directory..."
    mkdir -p /var/www/html/relatorios
    chown -R www-data:www-data /var/www/html/relatorios
fi

# Ensure proper permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/img
chown -R www-data:www-data /var/www/html/relatorios
chmod -R 755 /var/www/html/img
chmod -R 755 /var/www/html/relatorios

# Start Apache
echo "Starting Apache..."
apache2-foreground