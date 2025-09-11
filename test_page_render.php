<?php
// Test cadastroObra.php page rendering
require_once __DIR__ . '/startup.php';

echo "=== Testing cadastroObra.php Page Rendering ===\n\n";

// Test 1: Database data
echo "1. Database Data:\n";
$listaEmpresas = $dao->buscaTodasEmpresas();
echo "   - Total empresas: " . count($listaEmpresas) . "\n";

$listaEmpresasContratantes = array_filter($listaEmpresas, function($empresa) {
    return $empresa['contratante_sn'] == 1;
});
echo "   - Contratantes: " . count($listaEmpresasContratantes) . "\n";

$listaEmpresasContratadas = array_filter($listaEmpresas, function($empresa) {
    return $empresa['contratante_sn'] == 0;
});
echo "   - Contratadas: " . count($listaEmpresasContratadas) . "\n\n";

// Test 2: Simulate page rendering
echo "2. Simulating Page Render:\n";

// Capture cadastroObra.php output
ob_start();
include 'cadastroObra.php';
$output = ob_get_clean();

// Check for key elements
echo "   Checking for page elements:\n";

// Check for form
if (strpos($output, '<form') !== false) {
    echo "   ✅ Form found\n";
} else {
    echo "   ❌ Form NOT found\n";
}

// Check for contratante select
if (strpos($output, 'name="contratante"') !== false) {
    echo "   ✅ Contratante select found\n";
    
    // Count contratante options
    preg_match_all('/<option[^>]*>.*?<\/option>/s', substr($output, strpos($output, 'name="contratante"'), 5000), $matches);
    echo "      - Options found: " . count($matches[0]) . "\n";
    
    // Check for specific companies
    if (strpos($output, 'GRUEN') !== false) {
        echo "      ✅ GRUEN companies found in dropdown\n";
    } else {
        echo "      ❌ GRUEN companies NOT found\n";
    }
} else {
    echo "   ❌ Contratante select NOT found\n";
}

// Check for contratada select
if (strpos($output, 'name="contratada"') !== false) {
    echo "   ✅ Contratada select found\n";
    
    // Check for VETEL
    if (strpos($output, 'VETEL') !== false) {
        echo "      ✅ VETEL found in dropdown\n";
    } else {
        echo "      ❌ VETEL NOT found\n";
    }
} else {
    echo "   ❌ Contratada select NOT found\n";
}

// Check for PHP errors in output
if (strpos($output, 'Warning:') !== false || strpos($output, 'Notice:') !== false || strpos($output, 'Fatal error:') !== false) {
    echo "\n   ⚠️ PHP errors detected in output!\n";
    
    // Extract error messages
    preg_match_all('/(Warning|Notice|Fatal error):.*?(?=<|$)/s', $output, $errors);
    foreach ($errors[0] as $error) {
        echo "      - " . trim($error) . "\n";
    }
} else {
    echo "   ✅ No PHP errors in output\n";
}

echo "\n3. Output Length: " . strlen($output) . " characters\n";

// Save output for inspection
file_put_contents('test_output.html', $output);
echo "\n4. Full output saved to test_output.html for inspection\n";