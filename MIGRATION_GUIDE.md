# Migration Guide - New Architecture Implementation

## Overview

This document guides you through migrating from the legacy codebase to the new secure, maintainable architecture implemented in `src/`.

## Architecture Changes

### Before (Legacy)
```
projeto_vetel/
├── models/
│   ├── DAO.php (1000+ lines, all responsibilities)
│   └── [Model].php (anemic models)
├── *.php (mixed concerns)
```

### After (New Architecture)
```
projeto_vetel/
├── src/
│   ├── Database/DatabaseManager.php (secure queries)
│   ├── Repository/[Entity]Repository.php (data access)
│   ├── Service/[Entity]Service.php (business logic)
│   ├── Validator/ (input validation)
│   ├── Security/ (session, CSRF)
│   ├── Cache/ (performance)
│   └── Exception/ (error handling)
├── bootstrap.php (dependency injection)
```

## Step-by-Step Migration

### Phase 1: Setup New Architecture

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Include Bootstrap in Existing Files**
   ```php
   // At the top of existing PHP files
   require_once __DIR__ . '/bootstrap.php';
   ```

3. **Test New Architecture**
   ```bash
   php examples/new_architecture_usage.php
   ```

### Phase 2: Migrate Database Operations

#### Before (Legacy DAO)
```php
// Old way - direct DAO usage
$dao = new DAO($pdo);
$obras = $dao->buscaTodasObras();

// Insecure query building
$query = "SELECT * FROM obra WHERE id_obra = " . $_GET['id'];
```

#### After (New Architecture)
```php
// New way - using service layer
$obraService = app('obra.service');
$obras = $obraService->listProjects();

// Secure parameterized queries
$db = app('db');
$obra = $db->selectOne("SELECT * FROM obra WHERE id_obra = :id", ['id' => $_GET['id']]);
```

### Phase 3: Implement Validation

#### Before (Legacy)
```php
// No validation
if ($_POST['nome'] == '') {
    $errors[] = 'Nome é obrigatório';
}
```

#### After (New Architecture)
```php
use Src\Validator\InputValidator;

$validator = InputValidator::make($_POST);
if (!$validator->validate([
    'nome' => 'required|min:3|max:100',
    'email' => 'required|email',
    'cpf' => 'required|cpf'
])) {
    $errors = $validator->getErrors();
}
```

### Phase 4: Secure File Uploads

#### Before (Legacy)
```php
// Insecure file upload
move_uploaded_file($_FILES['logo']['tmp_name'], 'uploads/' . $_FILES['logo']['name']);
```

#### After (New Architecture)
```php
use Src\Validator\FileUploadValidator;

$validator = new FileUploadValidator($_FILES['logo']);
if ($validator->validate()) {
    $filename = $validator->moveToSecureLocation('uploads/');
}
```

### Phase 5: Enhance Session Security

#### Before (Legacy)
```php
// Basic session
session_start();
$_SESSION['user_id'] = $userId;
```

#### After (New Architecture)
```php
$session = session();
$session->authenticate(['id' => $userId, 'name' => $userName]);

// Automatic fingerprinting and regeneration
// CSRF protection built-in
```

## Migration Examples

### Example 1: Migrating cadastroObra.php

#### Legacy Code
```php
if (isset($_POST['submit'])) {
    $obra = new Obra();
    $obra->fk_id_contratante = $_POST['contratante'];
    $obra->fk_id_contratada = $_POST['contratada'];
    $obra->descricao_resumo = $_POST['obra'];
    
    $dao->insereObra($obra);
}
```

#### Migrated Code
```php
require_once __DIR__ . '/bootstrap.php';

if (isset($_POST['submit'])) {
    try {
        $obraService = app('obra.service');
        $project = $obraService->createProject([
            'fk_id_contratante' => $_POST['contratante'],
            'fk_id_contratada' => $_POST['contratada'],
            'descricao_resumo' => $_POST['obra']
        ]);
        
        header("Location: cadastroObra.php?success=1");
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
    } catch (ServiceException $e) {
        $errorMessage = $e->getUserMessage();
    }
}
```

### Example 2: Migrating Data Listing

#### Legacy Code
```php
$listaEmpresas = $dao->buscaTodasEmpresas();
```

#### Migrated Code
```php
$empresaRepo = app('empresa.repository');
$empresas = $empresaRepo->getPaginated(['search' => $_GET['search'] ?? ''], 1, 20);
```

## Compatibility Layer

The new architecture is designed to work alongside legacy code. Use this transition period to gradually migrate components.

### Accessing New Services from Legacy Code
```php
// In any existing PHP file
require_once __DIR__ . '/bootstrap.php';

// Access new services
$obraService = app('obra.service');
$cache = cache();
$session = session();
```

### Legacy DAO Still Available
```php
// Legacy DAO continues to work
global $dao;
$obras = $dao->buscaTodasObras();

// But prefer new repositories
$obraRepo = app('obra.repository');
$obras = $obraRepo->findAll();
```

## Performance Improvements

### Database Optimization
- ✅ Prepared statements prevent SQL injection
- ✅ Query result caching reduces database load
- ✅ Connection pooling improves efficiency

### Caching Implementation
```php
// Cache expensive operations
$cache = cache();
$result = $cache->remember('expensive_query', function() {
    return $someExpensiveOperation();
}, 3600); // Cache for 1 hour
```

## Security Enhancements

### Input Validation
- ✅ Comprehensive validation rules
- ✅ Brazilian-specific validators (CPF, CNPJ)
- ✅ File upload security

### Session Security
- ✅ Fingerprinting prevents hijacking
- ✅ Automatic regeneration
- ✅ Secure cookie settings

### Database Security
- ✅ All queries parameterized
- ✅ No string concatenation
- ✅ Input sanitization

## Testing

### Run Architecture Tests
```bash
php examples/new_architecture_usage.php
```

### Validate Database Security
```bash
# Check for unsafe queries (should return empty)
grep -r "SELECT.*\$_" --include="*.php" src/
```

### Performance Testing
```bash
# Before migration
time php legacy_operation.php

# After migration
time php new_architecture_operation.php
```

## Common Migration Patterns

### 1. Form Processing
```php
// Old
if ($_POST['submit']) {
    $data = $_POST;
    $dao->insert($data);
}

// New
if ($_POST['submit']) {
    try {
        $service = app('entity.service');
        $result = $service->create($_POST);
        // Handle success
    } catch (ValidationException $e) {
        // Handle validation errors
    }
}
```

### 2. Data Retrieval
```php
// Old
$items = $dao->getAllItems();

// New
$repo = app('entity.repository');
$items = $repo->getPaginated($filters, $page, $perPage);
```

### 3. Error Handling
```php
// Old
if (!$result) {
    die('Error occurred');
}

// New
try {
    $service = app('entity.service');
    $result = $service->process($data);
} catch (ServiceException $e) {
    $errorMessage = $e->getUserMessage();
}
```

## Rollback Plan

If issues occur during migration:

1. **Remove bootstrap include**
   ```php
   // Comment out this line
   // require_once __DIR__ . '/bootstrap.php';
   ```

2. **Revert to legacy DAO**
   ```php
   // Use original DAO
   global $dao;
   $result = $dao->legacyMethod();
   ```

3. **Database rollback**
   - New architecture doesn't modify database structure
   - All changes are backward compatible

## Next Steps

1. ✅ **Phase 1**: Setup complete
2. 🔄 **Phase 2**: Migrate critical forms (cadastroObra.php, cadastroEmpresa.php)
3. ⏳ **Phase 3**: Migrate list pages with pagination
4. ⏳ **Phase 4**: Migrate report generation
5. ⏳ **Phase 5**: Add comprehensive logging
6. ⏳ **Phase 6**: Implement Redis caching

## Support

For questions or issues during migration:

1. Check examples in `examples/new_architecture_usage.php`
2. Review existing implementations in `src/`
3. Test individual components before full migration
4. Keep legacy code as fallback during transition

The new architecture is production-ready and provides significant improvements in security, performance, and maintainability while maintaining full backward compatibility.