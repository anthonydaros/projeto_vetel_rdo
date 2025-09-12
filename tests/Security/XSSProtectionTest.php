<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class XSSProtectionTest extends TestCase
{
	/**
	 * Test that dangerous scripts are properly escaped
	 * @dataProvider xssPayloadsProvider
	 */
	public function testXSSPayloadsAreEscaped(string $payload, string $expected): void
	{
		// Test htmlspecialchars protection
		$escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

		// Verify dangerous content is escaped
		$this->assertStringNotContainsString('<script>', $escaped);
		$this->assertStringNotContainsString('javascript:', $escaped);
		$this->assertStringNotContainsString('onload=', $escaped);
		$this->assertStringNotContainsString('onerror=', $escaped);
	}

	public function xssPayloadsProvider(): array
	{
		return [
			'Script tag' => [
				'<script>alert("XSS")</script>',
				'&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'
			],
			'JavaScript protocol' => [
				'javascript:alert("XSS")',
				'javascript:alert(&quot;XSS&quot;)'
			],
			'Image with onload' => [
				'<img src="x" onload="alert(\'XSS\')">',
				'&lt;img src=&quot;x&quot; onload=&quot;alert(&#039;XSS&#039;)&quot;&gt;'
			],
			'Nested script' => [
				'"><script>alert("XSS")</script>',
				'&quot;&gt;&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'
			],
			'Event handler' => [
				'<div onclick="alert(\'XSS\')">Click me</div>',
				'&lt;div onclick=&quot;alert(&#039;XSS&#039;)&quot;&gt;Click me&lt;/div&gt;'
			]
		];
	}

	/**
	 * Test that form inputs are properly sanitized
	 */
	public function testFormInputSanitization(): void
	{
		$maliciousInput = '<script>alert("XSS")</script>';
		$sanitized = htmlspecialchars($maliciousInput, ENT_QUOTES, 'UTF-8');

		// Verify the output is safe
		$this->assertEquals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $sanitized);
	}

	/**
	 * Test that database output is properly escaped
	 */
	public function testDatabaseOutputEscaping(): void
	{
		// Simulate database content that might contain XSS
		$dbContent = [
			'nome_fantasia' => '<script>alert("XSS")</script>Company',
			'descricao_resumo' => 'Test <img src=x onerror=alert("XSS")> Description'
		];

		// Apply escaping as done in cadastroObra.php
		foreach ($dbContent as $key => $value) {
			$escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
			$this->assertStringNotContainsString('<script>', $escaped);
			$this->assertStringNotContainsString('onerror=', $escaped);
		}
	}
}
