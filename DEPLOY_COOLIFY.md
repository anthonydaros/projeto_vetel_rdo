# 🚀 Deployment Guide for Coolify

## Domain Configuration
- **Production URL**: https://rdo.vetel.ind.br/
- **SSL**: Automatically handled by Coolify via Let's Encrypt

## 📋 Pre-Deployment Checklist

### 1. Database Setup
Ensure your external MySQL database is:
- ✅ Accessible from the VPS IP
- ✅ Has the database `formulario_bd` created
- ✅ User has full permissions on the database
- ✅ Firewall allows connections from VPS

Test connection:
```bash
mysql -h your-db-host -u your-username -p formulario_bd -e "SELECT 1"
```

## 🔧 Coolify Configuration

### Step 1: Create New Application
1. In Coolify Dashboard → **New Application**
2. Select **Docker Compose**
3. Choose your server
4. Connect your Git repository

### Step 2: Build Configuration
```yaml
Build Pack: Docker Compose
Base Directory: /
Docker Compose Location: docker-compose.coolify.yml
```

### Step 3: Environment Variables
Add these required variables in Coolify's Environment Variables section:

```bash
# Database Configuration (REQUIRED)
DB_HOST=your-mysql-host.com
DB_PORT=3306
DB_NAME=formulario_bd
DB_USERNAME=your_database_user
DB_PASSWORD=your_secure_password

# Admin Configuration (REQUIRED)
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@vetel.ind.br

# Application Settings
APP_ENV=production
APP_DEBUG=false
TIMEZONE=America/Sao_Paulo

# Session Configuration
SESSION_LIFETIME=3600
SESSION_SECURE_COOKIE=true

# Optional: Email Configuration
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@vetel.ind.br
MAIL_FROM_NAME=RDO Vetel
```

### Step 4: Domain Configuration
1. Go to **Domains** section
2. Add domain: `rdo.vetel.ind.br`
3. Enable **Force HTTPS**
4. Enable **Auto-generate SSL**

### Step 5: Storage Configuration
The application will automatically:
- Create necessary directories on startup
- Set correct permissions (777 for uploads)
- Configure ownership for www-data
- Test write permissions

Persistent volumes will be created at:
- `uploads` - Photo storage
- `reports` - PDF/Excel reports
- `sessions` - PHP sessions
- `logs` - Application logs

## 🚀 Deployment

### Initial Deployment
1. Click **Deploy** in Coolify
2. Monitor build logs
3. Wait for health check to pass
4. Access https://rdo.vetel.ind.br/health.php to verify

### Post-Deployment Tasks

#### 1. Verify Health Status
```bash
curl https://rdo.vetel.ind.br/health.php
```

Expected response:
```json
{
  "status": "healthy",
  "checks": {
    "database": {"status": "pass"},
    "php_extensions": {"status": "pass"},
    "writable_album": {"status": "pass"}
  }
}
```

#### 2. Change Admin Password
1. Login at https://rdo.vetel.ind.br/
2. Use the admin credentials from environment variables
3. **Immediately change the password**

#### 3. Test File Uploads
1. Create a new RDO (Relatório Diário de Obra)
2. Upload test images
3. Verify images are saved and accessible

#### 4. Configure Backups
In your VPS, create a backup script:
```bash
#!/bin/bash
# Backup uploads daily
tar -czf /backups/uploads-$(date +%Y%m%d).tar.gz \
    /data/coolify/applications/*/storage/uploads/
    
# Keep only last 30 days
find /backups -name "uploads-*.tar.gz" -mtime +30 -delete
```

Add to crontab:
```bash
0 2 * * * /root/backup-uploads.sh
```

## 🔍 Troubleshooting

### Permission Issues
The container automatically fixes permissions on startup via the entrypoint script.

If uploads still fail, check container logs:
```bash
# View entrypoint logs
docker logs [container-id] | head -20

# The logs should show:
# ✓ Upload directory is writable
```

To manually fix:
```bash
# Restart the container to re-run entrypoint
docker restart [container-id]
```

### Database Connection Issues
1. Check environment variables in Coolify
2. Test connection from VPS:
```bash
docker exec -it [container-id] bash
mysql -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_NAME -e "SELECT 1"
```

### Memory Issues
If container crashes, increase memory limits in docker-compose.coolify.yml:
```yaml
deploy:
  resources:
    limits:
      memory: 2048M  # Increase as needed
```

## 📊 Monitoring

### Logs
View logs in Coolify dashboard or SSH:
```bash
# Application logs
docker logs [container-id]

# Apache logs
docker exec -it [container-id] tail -f /var/log/apache2/error.log

# PHP errors
docker exec -it [container-id] tail -f /var/log/apache2/php_errors.log
```

### Performance Monitoring
Access metrics at:
- Coolify Dashboard → Your App → Metrics
- Health endpoint: https://rdo.vetel.ind.br/health.php

## 🔄 Updates and Maintenance

### Updating Application
1. Push changes to Git repository
2. In Coolify: Click **Redeploy**
3. Monitor deployment logs
4. Verify health after deployment

### Database Maintenance
```sql
-- Optimize tables monthly
OPTIMIZE TABLE diario_obra, imagem, servico, funcionario_diario_obra;

-- Check table sizes
SELECT 
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'formulario_bd'
ORDER BY (data_length + index_length) DESC;
```

### Clean Old Sessions
```bash
# Run weekly
docker exec -it [container-id] bash -c \
    "find /var/www/sessions -type f -mtime +7 -delete"
```

## 🔒 Security Recommendations

1. **Firewall Rules**
   - Allow only ports 80, 443, and SSH
   - Restrict MySQL to specific IPs

2. **Regular Updates**
   ```bash
   # Update base image monthly
   docker pull php:8.2-apache
   # Rebuild and redeploy in Coolify
   ```

3. **SSL Configuration**
   - Already handled by Coolify/Traefik
   - Automatic renewal via Let's Encrypt

4. **Backup Strategy**
   - Daily: Upload files
   - Daily: Database dumps
   - Weekly: Full application backup
   - Monthly: Off-site backup

## 📞 Support

### Health Check Endpoints
- Main: https://rdo.vetel.ind.br/health.php
- Database: Test via health endpoint
- Uploads: Test via application

### Common Issues Reference
| Issue | Solution |
|-------|----------|
| 500 Error | Check PHP error logs |
| Upload fails | Check permissions on upload directory |
| Database timeout | Verify DB host allows VPS IP |
| Slow performance | Check memory usage and OPcache |
| Session expires | Adjust SESSION_LIFETIME |

## ✅ Production Checklist
- [ ] Domain DNS pointing to VPS
- [ ] Database accessible from VPS
- [ ] Environment variables configured
- [ ] Admin password changed
- [ ] File uploads tested
- [ ] Backups configured
- [ ] Monitoring enabled
- [ ] SSL certificate active
- [ ] Health endpoint responding
- [ ] Error logging configured

---

**Last Updated**: December 2024
**Application**: Projeto Vetel - RDO System
**Domain**: https://rdo.vetel.ind.br/