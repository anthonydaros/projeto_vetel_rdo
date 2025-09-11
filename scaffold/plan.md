# RDO Project Scaffolding Plan

## Pattern Analysis Complete ✓

### Project Structure
```
projeto_vetel/
├── models/           # Data models with DAO pattern
├── auth/            # Authentication system
├── config/          # Configuration classes
├── css/             # Bootstrap styles
├── js/              # JavaScript files
├── img/             # Images and logos
├── vendor/          # Composer dependencies
└── *.php            # Controllers and views
```

### Discovered Patterns

#### 1. Model Pattern
- Location: `models/`
- Class structure: Private properties with `__get`/`__set` magic methods
- Naming: PascalCase for classes, snake_case for properties
- Example: `Obra`, `Empresa`, `Funcionario`

#### 2. Controller Pattern
- Location: Root directory
- Naming convention:
  - `cadastro{Entity}.php` - Create/Update forms
  - `lista{Entity}s.php` - List views
  - `{operation}{Entity}.php` - Special operations
- Structure: Form handling with POST/GET processing

#### 3. DAO Pattern
- Single DAO class handling all entities
- Methods: `insere{Entity}`, `busca{Entity}Por{Field}`, `lista{Entity}s`, `delete{Entity}`
- PDO prepared statements for security

#### 4. Authentication
- Session-based authentication
- `Auth::requireAuth()` for page protection
- Access levels: User, Supervisor, Admin

## Available Scaffolding Templates

### 1. CRUD Module
Creates complete CRUD functionality for an entity:
- Model class
- Cadastro (create/update) page
- Lista (list) page
- DAO methods
- Authentication protection

### 2. Report Module
Creates reporting functionality:
- Data collector
- Report generator
- PDF exporter
- List view

### 3. API Endpoint
Creates RESTful API endpoint:
- Controller with JSON responses
- Authentication middleware
- DAO methods
- Documentation

### 4. Dashboard Component
Creates dashboard widget:
- Statistics collector
- View component
- JavaScript interactions
- Responsive design

## Usage Examples

```bash
# Create a new CRUD module
/scaffold "Material" crud

# Create a report module
/scaffold "MonthlyReport" report

# Create API endpoint
/scaffold "api/materials" api

# Resume previous scaffolding
/scaffold resume

# Check status
/scaffold status
```

## Session Management

- Session files stored in `scaffold/` directory
- Automatic session resume on next `/scaffold` command
- Progress tracking in `state.json`
- Pattern cache in `patterns.json`

## Ready to Scaffold

The scaffolding system is now initialized and ready to create new features following your project's established patterns.

To create a new feature, use:
```
/scaffold [FeatureName] [Type]
```

Available types:
- `crud` - Full CRUD module
- `report` - Reporting module
- `api` - API endpoint
- `dashboard` - Dashboard component