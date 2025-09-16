#!/bin/bash
#
# Fix permissions for Docker volumes
# Run this inside the Docker container if photos are not accessible
#

echo "=== FIXING DOCKER PERMISSIONS ==="

# Photo directories
PHOTO_DIR="/var/www/html/img/album"
LOGO_DIR="/var/www/html/img/logo"
REPORT_DIR="/var/www/html/relatorios"

# Check if running in Docker
if [ ! -f /.dockerenv ]; then
    echo "Warning: Not running inside Docker container"
    echo "This script should be run inside the Docker container"
    exit 1
fi

# Create directories if they don't exist
echo "1. Creating directories..."
mkdir -p $PHOTO_DIR
mkdir -p $LOGO_DIR
mkdir -p $REPORT_DIR

# Fix ownership
echo "2. Fixing ownership..."
chown -R www-data:www-data /var/www/html/img
chown -R www-data:www-data $REPORT_DIR

# Fix permissions
echo "3. Fixing permissions..."
chmod -R 755 /var/www/html/img
chmod -R 777 $PHOTO_DIR
chmod -R 777 $REPORT_DIR

# Count files
echo ""
echo "4. File count:"
PHOTO_COUNT=$(find $PHOTO_DIR -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" \) 2>/dev/null | wc -l)
echo "   Photos in album: $PHOTO_COUNT"

LOGO_COUNT=$(find $LOGO_DIR -type f 2>/dev/null | wc -l)
echo "   Logos: $LOGO_COUNT"

# Test write permissions
echo ""
echo "5. Testing write permissions..."
TEST_FILE="$PHOTO_DIR/.test_write"
if touch $TEST_FILE 2>/dev/null; then
    echo "   ✅ Write permission OK for $PHOTO_DIR"
    rm $TEST_FILE
else
    echo "   ❌ Cannot write to $PHOTO_DIR"
fi

# Check specific diary 524 images
echo ""
echo "6. Checking diary 524 images:"
DIARY_524_COUNT=$(ls -la $PHOTO_DIR/diario-524-* 2>/dev/null | wc -l)
if [ $DIARY_524_COUNT -gt 0 ]; then
    echo "   Found $DIARY_524_COUNT images for diary 524"
    ls -la $PHOTO_DIR/diario-524-* 2>/dev/null | head -5
else
    echo "   No images found for diary 524"
fi

echo ""
echo "=== DONE ==="
echo ""
echo "To run this script in Docker:"
echo "  docker-compose exec app bash /var/www/html/fix_docker_permissions.sh"
echo ""
echo "Or copy and run:"
echo "  docker cp fix_docker_permissions.sh CONTAINER_NAME:/tmp/"
echo "  docker exec CONTAINER_NAME bash /tmp/fix_docker_permissions.sh"