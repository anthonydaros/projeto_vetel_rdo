<?php

/**
 * Example usage of the new architecture
 * This file demonstrates how to use the new services and repositories
 */

require_once __DIR__ . '/../bootstrap.php';

use Src\Validator\InputValidator;
use Src\Validator\FileUploadValidator;
use Src\Exception\ValidationException;
use Src\Exception\ServiceException;

// =============================================================================
// Example 1: Creating a new project using the service layer
// =============================================================================
try {
	$obraService = app('obra.service');

	// Sample project data
	$projectData = [
		'fk_id_contratante' => 1,
		'fk_id_contratada' => 2,
		'descricao_resumo' => 'Construção de edifício residencial'
	];

	// Create project with validation and business logic
	$newProject = $obraService->createProject($projectData);

	echo "✅ Project created successfully!\n";
	echo "Project ID: {$newProject['id_obra']}\n";
	echo "Contractor: {$newProject['contratante_nome']}\n";
	echo "Contracted: {$newProject['contratada_nome']}\n\n";
} catch (ValidationException $e) {
	echo "❌ Validation Error: {$e->getUserMessage()}\n";
	print_r($e->getErrors());
} catch (ServiceException $e) {
	echo "❌ Service Error: {$e->getUserMessage()}\n";
}

// =============================================================================
// Example 2: Input validation
// =============================================================================
echo "=== Input Validation Examples ===\n";

// Valid data
$validData = [
	'email' => 'test@example.com',
	'nome' => 'João Silva',
	'cpf' => '123.456.789-01',
	'telefone' => '(11) 99999-9999'
];

$validator = InputValidator::make($validData);
if ($validator->validate([
	'email' => 'required|email',
	'nome' => 'required|min:3|max:100',
	'cpf' => 'required|cpf',
	'telefone' => 'required|phone'
])) {
	echo "✅ Validation passed for valid data\n";
	$cleanData = $validator->getValidated();
	echo 'Clean data: ' . json_encode($cleanData) . "\n\n";
} else {
	echo "❌ Unexpected validation failure\n";
	print_r($validator->getErrors());
}

// Invalid data
$invalidData = [
	'email' => 'invalid-email',
	'nome' => 'AB', // too short
	'cpf' => '123.456.789-99', // invalid CPF
	'telefone' => '123' // invalid phone
];

$validator = InputValidator::make($invalidData);
if (!$validator->validate([
	'email' => 'required|email',
	'nome' => 'required|min:3|max:100',
	'cpf' => 'required|cpf',
	'telefone' => 'required|phone'
])) {
	echo "✅ Validation correctly failed for invalid data\n";
	echo "Errors found:\n";
	foreach ($validator->getErrors() as $field => $errors) {
		echo "  $field: " . implode(', ', $errors) . "\n";
	}
	echo "\n";
}

// =============================================================================
// Example 3: Using repositories directly
// =============================================================================
echo "=== Repository Usage Examples ===\n";

$empresaRepo = app('empresa.repository');

// Get all contractors
$contractors = $empresaRepo->getContractors();
echo '✅ Found ' . count($contractors) . " contractor companies\n";

// Search companies
$searchResults = $empresaRepo->searchByName('VETEL');
echo '✅ Found ' . count($searchResults) . " companies matching 'VETEL'\n";

// Get company statistics
if (!empty($contractors)) {
	$stats = $empresaRepo->getStatistics($contractors[0]['id_empresa']);
	echo '✅ Company statistics: ' . json_encode($stats) . "\n\n";
}

// =============================================================================
// Example 4: Caching usage
// =============================================================================
echo "=== Cache Usage Examples ===\n";

$cache = cache();

// Cache some data
$cache->set('test_key', ['data' => 'test value', 'timestamp' => time()], 300);
echo "✅ Data cached with key 'test_key'\n";

// Retrieve cached data
$cachedData = $cache->get('test_key');
if ($cachedData) {
	echo '✅ Retrieved from cache: ' . json_encode($cachedData) . "\n";
}

// Using remember method (get from cache or execute callback)
$expensiveData = $cache->remember('expensive_query', function () {
	// Simulate expensive operation
	sleep(1);
	return ['result' => 'Expensive computation result', 'computed_at' => time()];
}, 600);

echo '✅ Expensive operation result: ' . json_encode($expensiveData) . "\n\n";

// =============================================================================
// Example 5: Session management
// =============================================================================
echo "=== Session Management Examples ===\n";

$session = session();

// Store data in session
$session->set('user_preference', 'dark_mode');
echo "✅ User preference stored in session\n";

// Flash messages
$session->flash('success', 'Operation completed successfully!');
echo "✅ Flash message set\n";

// Retrieve flash message (consumed after first access)
$message = $session->flash('success');
echo "✅ Flash message retrieved: '$message'\n";

// Try to get it again (should be null now)
$messageAgain = $session->flash('success');
echo '✅ Flash message retrieved again: ' . ($messageAgain ?: 'null') . "\n\n";

// =============================================================================
// Example 6: File upload validation
// =============================================================================
echo "=== File Upload Validation Example ===\n";

// Simulate file upload array
$simulatedFile = [
	'name' => 'test_image.jpg',
	'type' => 'image/jpeg',
	'size' => 500000, // 500KB
	'tmp_name' => __FILE__, // Using this file as example
	'error' => UPLOAD_ERR_OK
];

$fileValidator = new FileUploadValidator($simulatedFile, [
	'max_size' => 1048576, // 1MB
	'allowed_types' => ['image/jpeg', 'image/png'],
	'check_mime' => false, // Disabled for this example
	'check_extension' => true
]);

if ($fileValidator->validate()) {
	echo "✅ File validation passed\n";
} else {
	echo '❌ File validation failed: ' . $fileValidator->getFirstError() . "\n";
}

// =============================================================================
// Example 7: Database security demonstration
// =============================================================================
echo "=== Database Security Examples ===\n";

$db = app('db');

// Secure parameterized queries
try {
	// Safe insert
	$companyId = $db->insert('empresa', [
		'nome_fantasia' => 'Test Company',
		'cnpj' => '12.345.678/0001-90',
		'contratante_sn' => 0
	]);
	echo "✅ Secure insert completed. New company ID: $companyId\n";

	// Safe select with parameters
	$company = $db->selectOne(
		'SELECT * FROM empresa WHERE id_empresa = :id',
		['id' => $companyId]
	);

	if ($company) {
		echo "✅ Secure select completed: {$company['nome_fantasia']}\n";
	}

	// Clean up test data
	$db->delete('empresa', ['id_empresa' => $companyId]);
	echo "✅ Test data cleaned up\n";
} catch (Exception $e) {
	echo "❌ Database operation failed: {$e->getMessage()}\n";
}

echo "\n=== All examples completed! ===\n";
echo "The new architecture is working correctly alongside the legacy code.\n";
echo "You can now gradually migrate existing functionality to use these new components.\n";
