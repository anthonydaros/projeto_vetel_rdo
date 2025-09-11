<?php
require_once __DIR__ . '/startup.php';

echo "<h2>Testing cadastroObra.php data loading</h2>";

// Test 1: Check database connection
echo "<h3>1. Database Connection:</h3>";
if ($dao) {
    echo "✅ DAO initialized<br>";
} else {
    echo "❌ DAO NOT initialized<br>";
}

// Test 2: Fetch all empresas
echo "<h3>2. Fetching all empresas:</h3>";
$listaEmpresas = $dao->buscaTodasEmpresas();
echo "Total empresas found: " . count($listaEmpresas) . "<br>";

// Test 3: Filter contratantes
echo "<h3>3. Filtering contratantes (contratante_sn = 1):</h3>";
$listaEmpresasContratantes = array_filter($listaEmpresas, function($empresa) {
    return $empresa['contratante_sn'] == 1;
});
echo "Total contratantes: " . count($listaEmpresasContratantes) . "<br>";
echo "Contratantes list:<br>";
foreach ($listaEmpresasContratantes as $empresa) {
    echo "- " . htmlspecialchars($empresa['nome_fantasia']) . " (ID: " . $empresa['id_empresa'] . ")<br>";
}

// Test 4: Filter contratadas
echo "<h3>4. Filtering contratadas (contratante_sn = 0):</h3>";
$listaEmpresasContratadas = array_filter($listaEmpresas, function($empresa) {
    return $empresa['contratante_sn'] == 0;
});
echo "Total contratadas: " . count($listaEmpresasContratadas) . "<br>";
echo "Contratadas list:<br>";
foreach ($listaEmpresasContratadas as $empresa) {
    echo "- " . htmlspecialchars($empresa['nome_fantasia']) . " (ID: " . $empresa['id_empresa'] . ")<br>";
}

// Test 5: Check what would be in the dropdowns
echo "<h3>5. Testing dropdown HTML generation:</h3>";
echo "<h4>Contratante dropdown would contain:</h4>";
echo "<pre>";
echo htmlspecialchars('<select class="custom-select" name="contratante" id="contratante">') . "\n";
echo htmlspecialchars('    <option value="" selected class="text-secondary">Selecionar empresa contratante</option>') . "\n";
foreach ($listaEmpresasContratantes as $empresa) {
    echo htmlspecialchars('    <option value="' . $empresa['nome_fantasia'] . '">' . $empresa['nome_fantasia'] . '</option>') . "\n";
}
echo htmlspecialchars('</select>') . "\n";
echo "</pre>";

echo "<h4>Contratada dropdown would contain:</h4>";
echo "<pre>";
echo htmlspecialchars('<select class="custom-select" name="contratada" id="contratada">') . "\n";
echo htmlspecialchars('    <option value="" selected class="text-secondary">Selecionar empresa contratada</option>') . "\n";
foreach ($listaEmpresasContratadas as $empresa) {
    echo htmlspecialchars('    <option value="' . $empresa['nome_fantasia'] . '">' . $empresa['nome_fantasia'] . '</option>') . "\n";
}
echo htmlspecialchars('</select>') . "\n";
echo "</pre>";

// Test 6: Check PHP configuration
echo "<h3>6. PHP Configuration:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Short tags enabled: " . (ini_get('short_open_tag') ? 'Yes' : 'No') . "<br>";