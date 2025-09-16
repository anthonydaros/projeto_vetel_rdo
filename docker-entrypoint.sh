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

# Create directory if it doesn't exist
if [ ! -d "$PHOTO_DIR" ]; then
    echo "Creating uploads directory..."
    mkdir -p "$PHOTO_DIR"
fi

# Always sync photos from backup to volume
echo "Checking photo backup directory: $PHOTO_BACKUP"
BACKUP_COUNT=$(find "$PHOTO_BACKUP" -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.webp" \) 2>/dev/null | wc -l)
echo "Photos available in backup: $BACKUP_COUNT"

if [ -d "$PHOTO_BACKUP" ] && [ "$BACKUP_COUNT" -gt 0 ]; then
    echo "Syncing photos from backup to volume..."

    # Count files before sync
    BEFORE_COUNT=$(find "$PHOTO_DIR" -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.webp" \) 2>/dev/null | wc -l)
    echo "Photos in volume before sync: $BEFORE_COUNT"

    # Check if volume needs full initialization (less than 100 photos means incomplete)
    if [ "$BEFORE_COUNT" -lt 100 ]; then
        echo "Volume appears incomplete (only $BEFORE_COUNT photos), performing full sync..."
        # Force copy ALL files from backup
        cp -Rf "$PHOTO_BACKUP/"* "$PHOTO_DIR/" 2>/dev/null || true
    else
        echo "Volume has sufficient photos, performing incremental sync..."
        # Use cp -n to copy only files that don't exist in destination
        cp -Rn "$PHOTO_BACKUP/"* "$PHOTO_DIR/" 2>/dev/null || true
    fi

    # Count files after sync
    AFTER_COUNT=$(find "$PHOTO_DIR" -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.webp" \) 2>/dev/null | wc -l)
    NEW_FILES=$((AFTER_COUNT - BEFORE_COUNT))

    echo "Photos in volume after sync: $AFTER_COUNT"
    echo "New photos synced: $NEW_FILES"
else
    echo "No backup directory found, skipping photo sync"
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