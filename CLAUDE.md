# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP-based construction management system for generating and controlling RDO (Relatório Diário de Obra - Daily Work Report) and RDP reports. The system manages construction sites, companies, employees, and daily work reports with photo documentation.

## Database Configuration

The database connection is configured through environment variables in `.env` file. The system uses MySQL/MariaDB with PDO for database operations. 

**Security Improvements Implemented**:
- Database credentials now stored in `.env` file (excluded from version control)
- Environment-based configuration through `Config\Config` class
- Secure PDO settings with prepared statements and error handling

## Architecture

### Core Components

1. **Models** (`/models/`):
   - `Connection.php`: Database connection singleton
   - `DAO.php`: Data Access Object with all database operations (insert, update, delete, select)
   - Entity models: `Empresa.php`, `Funcionario.php`, `Obra.php`, `DiarioObra.php`, `Servico.php`, `FuncionarioDiarioObra.php`, `Imagem.php`

2. **Main Application Files**:
   - `startup.php`: Bootstrap file that initializes database connection and includes all models
   - `index.php`: Main navigation interface
   - CRUD pages: `cadastro*.php` (create), `lista*.php` (list/read)
   - Report generators: `rdo.php`, `rdp.php`, `geradorRdp.php`
   - PDF export: `exportadorPdf.php`

3. **Assets**:
   - `/img/album/`: Photo storage for daily reports
   - `/relatorios/`: Generated reports storage
   - `/vendor/`: Composer dependencies

### Database Schema

Key tables (defined in `/sql/formulario_bd.sql`):
- `empresa`: Companies (contractors and contracted)
- `obra`: Construction projects
- `funcionario`: Employees
- `diario_obra`: Daily work reports
- `imagem`: Photos linked to daily reports
- `servico`: Services performed
- `funcionario_diario_obra`: Many-to-many relationship between employees and daily reports

## Development Commands

```bash
# Install dependencies
composer install

# Run local PHP server (development)
php -S localhost:8000

# Update dependencies
composer update

# Initial setup (run once to create admin user)
php admin/setup.php
# Or via browser: http://localhost:8000/admin/setup.php?token=CHANGE_THIS_TOKEN_YYYYMMDD

# Create password hash for new users
php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
```

## Dependencies

Key PHP packages (via Composer):
- `dompdf/dompdf`: PDF generation for reports
- `phpoffice/phpspreadsheet`: Excel file handling
- `google/cloud-translate`: Translation services
- `stichoza/google-translate-php`: Alternative translation API

## File Upload

The system uses Dropzone.js (v5.7.0) for photo uploads. Implementation is in `/dropzone-5.7.0/`.

## Important Considerations

1. **Configuration**: System configuration is managed through `.env` file. Copy `.env.example` to `.env` and configure for your environment.

2. **Authentication**: The system now includes a basic authentication system:
   - User authentication with secure password hashing
   - Session management with timeout
   - Access level control (User/Supervisor/Admin)
   - Login tracking and access logs
   - Protected pages require authentication via `Auth::requireAuth()`

3. **Security Features**:
   - Environment-based configuration
   - Secure session handling
   - Password hashing using bcrypt
   - SQL injection prevention through PDO prepared statements
   - XSS protection through output escaping
   - Error logging in production mode

4. **Session Management**: The system uses `startup.php` as a bootstrap file with secure session configuration.

5. **Error Handling**: Error display controlled by `APP_DEBUG` environment variable - disabled in production.

6. **Time Zone**: Configurable via `TIMEZONE` environment variable (default: 'America/Sao_Paulo').

7. **Photo Storage**: Photos stored in configurable path via `PHOTO_STORAGE_PATH` environment variable.

## Common Development Tasks

### Adding a New Entity

1. Create model class in `/models/`
2. Add corresponding CRUD methods in `models/DAO.php`
3. Create UI pages: `cadastro{Entity}.php` and `lista{Entity}.php`
4. Include model in `startup.php`
5. Update database schema if needed

### Generating Reports

Reports are generated through:
- `rdo.php`: Daily work report display
- `rdp.php`: Period report display  
- `exportadorPdf.php`: PDF export functionality using DOMPDF

### Database Operations

All database operations go through the DAO class pattern:
- Insert: `$dao->insere{Entity}($object)`
- Update: `$dao->atualiza{Entity}($object)`
- Delete: `$dao->deleta{Entity}($id)`
- Select: `$dao->busca{Entity}()` or `$dao->busca{Entity}PorId($id)`

### Security Setup

1. **First Time Setup**:
   ```bash
   # Run setup script to create database tables and admin user
   php admin/setup.php
   # Default admin credentials: admin / admin123
   # CHANGE PASSWORD IMMEDIATELY!
   ```

2. **Adding Authentication to Pages**:
   ```php
   // At the top of any protected page
   require_once __DIR__ . '/auth/Auth.php';
   use Auth\Auth;
   Auth::requireAuth(); // Redirects to login if not authenticated
   ```

3. **Environment Configuration**:
   - Copy `.env.example` to `.env`
   - Configure database credentials
   - Set `APP_DEBUG=false` for production
   - Set `APP_ENV=production` for production