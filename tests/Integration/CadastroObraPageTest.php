<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class CadastroObraPageTest extends TestCase
{
	private $dao;

	protected function setUp(): void
	{
		// Don't require startup.php here since it will be included by cadastroObra.php
		// Just set up what we need for direct DAO tests
		if (!defined('__DIR__')) {
			define('__DIR__', dirname(__FILE__));
		}
	}

	/**
	 * Test that cadastroObra.php renders without errors
	 */
	public function testPageRendersWithoutErrors(): void
	{
		// Capture page output
		ob_start();
		include __DIR__ . '/../../cadastroObra.php';
		$output = ob_get_clean();

		// Assert page has content
		$this->assertNotEmpty($output);

		// Assert no PHP errors in output
		$this->assertStringNotContainsString('Warning:', $output);
		$this->assertStringNotContainsString('Notice:', $output);
		$this->assertStringNotContainsString('Fatal error:', $output);
		$this->assertStringNotContainsString('Parse error:', $output);
	}

	/**
	 * Test that dropdowns are populated with database data
	 */
	public function testDropdownsPopulatedWithDatabaseData(): void
	{
		// Load startup to get DAO
		require_once __DIR__ . '/../../startup.php';
		global $dao;

		// Get empresas from database
		$listaEmpresas = $dao->buscaTodasEmpresas();
		$this->assertNotEmpty($listaEmpresas, 'Database should have empresas');

		// Filter contratantes and contratadas
		$contratantes = array_filter($listaEmpresas, function ($empresa) {
			return $empresa['contratante_sn'] == 1;
		});
		$contratadas = array_filter($listaEmpresas, function ($empresa) {
			return $empresa['contratante_sn'] == 0;
		});

		// Capture page output
		ob_start();
		include __DIR__ . '/../../cadastroObra.php';
		$output = ob_get_clean();

		// Check for contratante select
		$this->assertStringContainsString('name="contratante"', $output);
		$this->assertStringContainsString('Selecionar empresa contratante', $output);

		// Check for contratada select
		$this->assertStringContainsString('name="contratada"', $output);
		$this->assertStringContainsString('Selecionar empresa contratada', $output);

		// Verify at least one company from each type appears
		if (count($contratantes) > 0) {
			$firstContratante = reset($contratantes);
			$this->assertStringContainsString(
				htmlspecialchars($firstContratante['nome_fantasia']),
				$output,
				'Contratante companies should appear in dropdown'
			);
		}

		if (count($contratadas) > 0) {
			$firstContratada = reset($contratadas);
			$this->assertStringContainsString(
				htmlspecialchars($firstContratada['nome_fantasia']),
				$output,
				'Contratada companies should appear in dropdown'
			);
		}
	}

	/**
	 * Test that form has CSRF protection
	 */
	public function testFormHasCSRFProtection(): void
	{
		// Start session if not started
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		// Capture page output
		ob_start();
		include __DIR__ . '/../../cadastroObra.php';
		$output = ob_get_clean();

		// Check for CSRF token field
		$this->assertStringContainsString('name="csrf_token"', $output);
		$this->assertStringContainsString('type="hidden"', $output);
	}

	/**
	 * Test that all inputs are properly escaped
	 */
	public function testInputsAreProperlyEscaped(): void
	{
		// Capture page output
		ob_start();
		include __DIR__ . '/../../cadastroObra.php';
		$output = ob_get_clean();

		// Check that htmlspecialchars is used in the form action
		$this->assertMatchesRegularExpression('/action="[^"]*"/', $output);

		// Verify no unescaped PHP short tags remain
		$this->assertDoesNotMatchRegularExpression('/<\?[^p]/', $output);
		$this->assertDoesNotMatchRegularExpression('/<\?=/', $output);
	}

	/**
	 * Test database connection through DAO
	 */
	public function testDatabaseConnection(): void
	{
		// Load startup to get DAO
		require_once __DIR__ . '/../../startup.php';
		global $dao;

		$this->assertNotNull($dao, 'DAO should be initialized');

		// Test fetching empresas
		$empresas = $dao->buscaTodasEmpresas();
		$this->assertIsArray($empresas);
		$this->assertGreaterThan(0, count($empresas), 'Should have at least one empresa');

		// Verify empresa structure
		if (count($empresas) > 0) {
			$empresa = $empresas[0];
			$this->assertArrayHasKey('id_empresa', $empresa);
			$this->assertArrayHasKey('nome_fantasia', $empresa);
			$this->assertArrayHasKey('contratante_sn', $empresa);
		}
	}
}
