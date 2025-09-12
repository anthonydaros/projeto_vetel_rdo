# Deployment Checklist for Coolify

## ✅ Fixed Issues

### 1. Docker Build Issues
- [x] Created `docker-entrypoint.sh` for database initialization
- [x] Fixed `.dockerignore` to allow docker-entrypoint.sh
- [x] Resolved Composer install flags conflict
- [x] Added MySQL client package to Dockerfile
- [x] Fixed Apache LogFormat syntax error

### 2. Database Configuration
- [x] Configured external MariaDB connection:
  - Host: 103.199.185.165
  - Port: 5987
  - Database: default
  - User: mariadb
- [x] Updated `docker-compose.coolify.yml` with production credentials
- [x] Modified `Config.php` to handle environment variables without .env file

### 3. Docker Configuration Files
- [x] `Dockerfile`: Multi-stage build with PHP 8.2 and Apache
- [x] `docker-compose.coolify.yml`: Production configuration
- [x] `docker-entrypoint.sh`: Database check and Apache startup
- [x] `.dockerignore`: Optimized for production build

## 🚀 Deployment Steps for Coolify

1. **Push to GitHub** ✅
   - All changes committed and pushed to main branch

2. **Coolify Should:**
   - Pull latest code from GitHub
   - Build Docker image using `Dockerfile`
   - Use `docker-compose.coolify.yml` for configuration
   - Connect to external MariaDB at 103.199.185.165:5987
   - Start Apache on port 80

3. **Verification Steps:**
   - Check build logs for any errors
   - Verify container is running
   - Test application at https://rdo.vetel.ind.br
   - Should redirect to `/login.php` if not authenticated

## 📝 Environment Variables Required

```env
DB_HOST=103.199.185.165
DB_PORT=5987
DB_NAME=default
DB_USER=mariadb
DB_PASSWORD=hr6nhoC6TWfMyAoFZbhB4TPsKoomu00U0gGov1MIsiTihJiG4KTBYXJrpzW2g1n8
APP_ENV=production
APP_DEBUG=false
TIMEZONE=America/Sao_Paulo
```

## 🔍 Troubleshooting

If deployment fails:

1. **Check Build Logs:**
   - Ensure all Docker build steps complete
   - No missing files or dependencies

2. **Check Runtime Logs:**
   - Database connection successful
   - Apache started without errors

3. **Common Issues:**
   - Port conflicts (ensure port 80 is available)
   - Database connectivity (firewall/network issues)
   - Volume permissions for `/img/album` and `/relatorios`

## ✨ Local Testing

Test locally with:
```bash
docker-compose -f docker-compose.test.yml up
```
Access at: http://localhost:8080

## 📦 Latest Commits

- `1ee5357` Add MySQL client and fix Apache configuration
- `5b3a4a7` Configure for external MariaDB database
- `9d7bb8d` Fix dockerignore to allow docker-entrypoint.sh
- `2ff9495` Add fallback to composer update
- `eb715b3` Fix composer install flags conflict