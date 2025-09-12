#!/bin/bash
set -e

# Colors for better log visibility
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Timestamp function
timestamp() {
    date '+%Y-%m-%d %H:%M:%S'
}

# Logging function
log() {
    echo -e "${2:-$NC}[$(timestamp)] $1${NC}"
}

# Header
echo "=========================================="
log "PROJETO VETEL - RDO SYSTEM" "$CYAN"
log "Container Initialization Starting" "$CYAN"
log "Domain: https://rdo.vetel.ind.br/" "$CYAN"
echo "=========================================="
echo ""

# System Information
log "SYSTEM INFORMATION:" "$YELLOW"
log "  Hostname: $(hostname)" "$NC"
log "  Container ID: $(hostname)" "$NC"
log "  OS: $(cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)" "$NC"
log "  Kernel: $(uname -r)" "$NC"
log "  Architecture: $(uname -m)" "$NC"
echo ""

# PHP and Apache versions
log "SOFTWARE VERSIONS:" "$YELLOW"
log "  PHP: $(php -v | head -n1 | cut -d' ' -f2)" "$NC"
log "  Apache: $(apache2 -v | head -n1 | cut -d' ' -f3)" "$NC"
log "  Composer: $(composer --version 2>/dev/null | cut -d' ' -f3 || echo 'Not installed')" "$NC"
echo ""

# Environment check
log "ENVIRONMENT CONFIGURATION:" "$YELLOW"
log "  Environment: ${APP_ENV:-production}" "$NC"
log "  Debug Mode: ${APP_DEBUG:-false}" "$NC"
log "  Timezone: ${TIMEZONE:-America/Sao_Paulo}" "$NC"
log "  Max Upload: ${MAX_UPLOAD_SIZE:-10M}" "$NC"
log "  Session Lifetime: ${SESSION_LIFETIME:-3600} seconds" "$NC"
echo ""

# Database configuration check
log "DATABASE CONFIGURATION:" "$YELLOW"
if [ -n "$DB_HOST" ]; then
    log "  Host: $DB_HOST" "$GREEN"
    log "  Port: ${DB_PORT:-3306}" "$GREEN"
    log "  Database: $DB_NAME" "$GREEN"
    log "  Username: $DB_USERNAME" "$GREEN"
    log "  Password: [HIDDEN]" "$GREEN"
else
    log "  WARNING: Database not configured!" "$RED"
fi
echo ""

# Directory setup
log "SETTING UP DIRECTORIES:" "$YELLOW"

# Create directories with detailed logging
DIRS=(
    "/var/www/html/img/album:Photo uploads"
    "/var/www/html/relatorios:PDF reports"
    "/var/www/sessions:PHP sessions"
    "/var/log/apache2:Apache logs"
)

for dir_info in "${DIRS[@]}"; do
    IFS=':' read -r dir_path dir_desc <<< "$dir_info"
    if [ ! -d "$dir_path" ]; then
        mkdir -p "$dir_path"
        log "  ✓ Created: $dir_path ($dir_desc)" "$GREEN"
    else
        log "  • Exists: $dir_path ($dir_desc)" "$BLUE"
    fi
done
echo ""

# Set permissions with logging
log "SETTING PERMISSIONS:" "$YELLOW"

# Upload directories need 777 for write access
for dir in /var/www/html/img/album /var/www/html/relatorios /var/www/sessions; do
    chmod -R 777 "$dir"
    log "  ✓ Set 777: $dir" "$GREEN"
done

# Log directory needs 755
chmod -R 755 /var/log/apache2
log "  ✓ Set 755: /var/log/apache2" "$GREEN"
echo ""

# Set ownership with logging
log "SETTING OWNERSHIP:" "$YELLOW"
for dir in /var/www/html/img/album /var/www/html/relatorios /var/www/sessions /var/log/apache2; do
    chown -R www-data:www-data "$dir"
    log "  ✓ Owner www-data: $dir" "$GREEN"
done
echo ""

# Create .env file from environment variables
log "ENVIRONMENT FILE SETUP:" "$YELLOW"
if [ ! -f /var/www/html/.env ]; then
    log "  Creating new .env file..." "$NC"
    cat > /var/www/html/.env << EOF
# Database Configuration
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

# Application Settings
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-https://rdo.vetel.ind.br}

# Timezone
TIMEZONE=${TIMEZONE:-America/Sao_Paulo}

# Session Configuration
SESSION_LIFETIME=${SESSION_LIFETIME:-3600}
SESSION_SECURE_COOKIE=${SESSION_SECURE_COOKIE:-true}

# Admin Credentials
ADMIN_USERNAME=${ADMIN_USERNAME:-admin}
ADMIN_PASSWORD=${ADMIN_PASSWORD}
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@vetel.ind.br}

# Photo Storage
PHOTO_STORAGE_PATH=${PHOTO_STORAGE_PATH:-/var/www/html/img/album}
MAX_UPLOAD_SIZE=${MAX_UPLOAD_SIZE:-10M}

# Security
JWT_SECRET=${JWT_SECRET}
APP_KEY=${APP_KEY}

# Email Configuration
MAIL_DRIVER=${MAIL_DRIVER:-smtp}
MAIL_HOST=${MAIL_HOST}
MAIL_PORT=${MAIL_PORT:-587}
MAIL_USERNAME=${MAIL_USERNAME}
MAIL_PASSWORD=${MAIL_PASSWORD}
MAIL_ENCRYPTION=${MAIL_ENCRYPTION:-tls}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-noreply@vetel.ind.br}
MAIL_FROM_NAME="${MAIL_FROM_NAME:-RDO Vetel}"
EOF
    chmod 644 /var/www/html/.env
    chown www-data:www-data /var/www/html/.env
    log "  ✓ Created .env file" "$GREEN"
else
    log "  • Using existing .env file" "$BLUE"
fi
echo ""

# Test write permissions with detailed output
log "TESTING WRITE PERMISSIONS:" "$YELLOW"

# Test each directory
TEST_DIRS=(
    "/var/www/html/img/album:Photo uploads"
    "/var/www/html/relatorios:Reports"
    "/var/www/sessions:Sessions"
)

for dir_info in "${TEST_DIRS[@]}"; do
    IFS=':' read -r dir_path dir_desc <<< "$dir_info"
    TEST_FILE="$dir_path/test_$(date +%s).txt"
    
    if echo "Write test at $(timestamp)" > "$TEST_FILE" 2>/dev/null; then
        log "  ✓ Writable: $dir_path ($dir_desc)" "$GREEN"
        rm -f "$TEST_FILE"
    else
        log "  ✗ NOT writable: $dir_path ($dir_desc)" "$RED"
    fi
done
echo ""

# Check disk space
log "DISK SPACE:" "$YELLOW"
df -h / | tail -1 | awk '{
    used_percent = substr($5, 1, length($5)-1)
    if (used_percent < 80)
        status = "✓"
    else
        status = "⚠"
    printf "  %s Disk usage: %s (Free: %s)\n", status, $5, $4
}'
echo ""

# Check memory
log "MEMORY INFORMATION:" "$YELLOW"
free -h | grep Mem | awk '{
    printf "  Total: %s | Used: %s | Free: %s\n", $2, $3, $4
}'
echo ""

# PHP Extensions check
log "PHP EXTENSIONS:" "$YELLOW"
REQUIRED_EXT=(pdo pdo_mysql gd zip intl opcache)
for ext in "${REQUIRED_EXT[@]}"; do
    if php -m | grep -q "^$ext$"; then
        log "  ✓ $ext enabled" "$GREEN"
    else
        log "  ✗ $ext MISSING" "$RED"
    fi
done
echo ""

# Test database connection
log "DATABASE CONNECTION TEST:" "$YELLOW"
if [ -n "$DB_HOST" ] && [ -n "$DB_USERNAME" ] && [ -n "$DB_PASSWORD" ]; then
    if php -r "
        try {
            \$pdo = new PDO(
                'mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_NAME}',
                '${DB_USERNAME}',
                '${DB_PASSWORD}',
                [PDO::ATTR_TIMEOUT => 5]
            );
            echo 'SUCCESS';
        } catch (Exception \$e) {
            echo 'FAILED: ' . \$e->getMessage();
        }
    " 2>/dev/null | grep -q "SUCCESS"; then
        log "  ✓ Database connection successful" "$GREEN"
    else
        log "  ✗ Database connection failed" "$RED"
        log "    Check your database credentials and network connectivity" "$YELLOW"
    fi
else
    log "  ⚠ Database credentials not configured" "$YELLOW"
fi
echo ""

# Apache configuration check
log "APACHE MODULES:" "$YELLOW"
REQUIRED_MODS=(rewrite headers expires)
for mod in "${REQUIRED_MODS[@]}"; do
    if apache2ctl -M 2>/dev/null | grep -q "${mod}_module"; then
        log "  ✓ mod_$mod enabled" "$GREEN"
    else
        log "  ✗ mod_$mod MISSING" "$RED"
    fi
done
echo ""

# Final summary
echo "=========================================="
log "INITIALIZATION COMPLETE" "$CYAN"
log "Container ready to serve requests" "$CYAN"
log "URL: https://rdo.vetel.ind.br/" "$CYAN"
log "Health Check: /health.php" "$CYAN"
echo "=========================================="
echo ""

# Start Apache with logging
log "STARTING APACHE WEB SERVER..." "$MAGENTA"
log "Listening on port 80" "$NC"
log "Document root: /var/www/html" "$NC"
log "Error log: /var/log/apache2/error.log" "$NC"
log "Access log: /var/log/apache2/access.log" "$NC"
echo ""

# Execute Apache in foreground
exec apache2-foreground