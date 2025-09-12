<?php

declare(strict_types=1);

namespace Src\Validator;

use Src\Exception\ValidationException;

/**
 * Validador específico para upload de imagens
 * Inclui validação de segurança, formato e tamanho
 */
class ImageUploadValidator extends FileUploadValidator
{
	private array $allowedMimeTypes;
	private array $allowedExtensions;
	private int $maxImageWidth;
	private int $maxImageHeight;

	public function __construct(array $file, array $config = [])
	{
		$defaultConfig = array_merge([
			'max_size' => 5 * 1024 * 1024, // 5MB
			'allowed_types' => [
				'image/jpeg',
				'image/jpg',
				'image/png',
				'image/webp'
			],
			'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
			'max_width' => 4096,
			'max_height' => 4096,
			'min_width' => 100,
			'min_height' => 100,
			'check_image_content' => true,
			'scan_malicious_code' => true
		], $config);

		parent::__construct($file, $defaultConfig);

		$this->allowedMimeTypes = $defaultConfig['allowed_types'];
		$this->allowedExtensions = $defaultConfig['allowed_extensions'];
		$this->maxImageWidth = $defaultConfig['max_width'];
		$this->maxImageHeight = $defaultConfig['max_height'];
	}

	public function validate(): bool
	{
		// Validações básicas do parent
		if (!parent::validate()) {
			return false;
		}

		// Validações específicas de imagem
		return $this->validateImageContent() &&
			   $this->validateImageDimensions() &&
			   $this->validateImageIntegrity() &&
			   $this->scanForMaliciousImageContent();
	}

	/**
	 * Valida se o arquivo é realmente uma imagem válida
	 */
	private function validateImageContent(): bool
	{
		if (!$this->config['check_image_content']) {
			return true;
		}

		$imageInfo = @getimagesize($this->file['tmp_name']);

		if ($imageInfo === false) {
			$this->errors[] = 'O arquivo não é uma imagem válida.';
			return false;
		}

		// Verifica se MIME type real corresponde ao detectado
		$realMimeType = $imageInfo['mime'];
		if (!in_array($realMimeType, $this->allowedMimeTypes)) {
			$this->errors[] = 'Tipo de imagem não permitido: ' . $realMimeType;
			return false;
		}

		// Verifica consistência entre extensão e tipo real
		$extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
		if (!$this->isConsistentImageType($extension, $realMimeType)) {
			$this->errors[] = 'Extensão do arquivo não corresponde ao tipo de imagem.';
			return false;
		}

		return true;
	}

	/**
	 * Valida dimensões da imagem
	 */
	private function validateImageDimensions(): bool
	{
		$imageInfo = @getimagesize($this->file['tmp_name']);

		if ($imageInfo === false) {
			return false; // Erro já reportado em validateImageContent
		}

		$width = $imageInfo[0];
		$height = $imageInfo[1];

		if ($width > $this->maxImageWidth || $height > $this->maxImageHeight) {
			$this->errors[] = sprintf(
				'Imagem muito grande: %dx%d. Máximo permitido: %dx%d',
				$width,
				$height,
				$this->maxImageWidth,
				$this->maxImageHeight
			);
			return false;
		}

		if ($width < $this->config['min_width'] || $height < $this->config['min_height']) {
			$this->errors[] = sprintf(
				'Imagem muito pequena: %dx%d. Mínimo necessário: %dx%d',
				$width,
				$height,
				$this->config['min_width'],
				$this->config['min_height']
			);
			return false;
		}

		return true;
	}

	/**
	 * Verifica integridade da imagem tentando carregá-la
	 */
	private function validateImageIntegrity(): bool
	{
		$imageInfo = @getimagesize($this->file['tmp_name']);

		if ($imageInfo === false) {
			return false;
		}

		$mimeType = $imageInfo['mime'];
		$resource = null;

		try {
			switch ($mimeType) {
				case 'image/jpeg':
					$resource = @imagecreatefromjpeg($this->file['tmp_name']);
					break;
				case 'image/png':
					$resource = @imagecreatefrompng($this->file['tmp_name']);
					break;
				case 'image/webp':
					$resource = function_exists('imagecreatefromwebp')
						? @imagecreatefromwebp($this->file['tmp_name'])
						: true; // Skip if WebP not available
					break;
				default:
					$resource = false;
			}

			if ($resource === false) {
				$this->errors[] = 'Imagem corrompida ou em formato inválido.';
				return false;
			}

			// Libera recursos se foi criado resource
			if (is_resource($resource)) {
				imagedestroy($resource);
			}

			return true;
		} catch (\Exception $e) {
			$this->errors[] = 'Erro ao validar integridade da imagem: ' . $e->getMessage();
			return false;
		}
	}

	/**
	 * Busca por conteúdo malicioso específico de imagens
	 */
	private function scanForMaliciousImageContent(): bool
	{
		if (!$this->config['scan_malicious_code']) {
			return true;
		}

		$content = file_get_contents($this->file['tmp_name']);

		// Padrões maliciosos específicos em imagens
		$maliciousPatterns = [
			// PHP code injection
			'/<\?php/i',
			'/<\?=/i',
			'/<script[^>]*>/i',
			'/javascript:/i',
			// Common exploit attempts
			'/eval\s*\(/i',
			'/exec\s*\(/i',
			'/system\s*\(/i',
			'/shell_exec/i',
			// SQL injection patterns
			'/union\s+select/i',
			'/drop\s+table/i',
			// XSS patterns in metadata
			'/on\w+\s*=/i',
			// Base64 encoded suspicious content
			'/data:(?:text\/html|application\/)/i'
		];

		foreach ($maliciousPatterns as $pattern) {
			if (preg_match($pattern, $content)) {
				$this->errors[] = 'A imagem contém conteúdo potencialmente malicioso.';
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifica consistência entre extensão e tipo MIME
	 */
	private function isConsistentImageType(string $extension, string $mimeType): bool
	{
		$validCombinations = [
			'jpg' => ['image/jpeg'],
			'jpeg' => ['image/jpeg'],
			'png' => ['image/png'],
			'webp' => ['image/webp']
		];

		return isset($validCombinations[$extension]) &&
			   in_array($mimeType, $validCombinations[$extension]);
	}

	/**
	 * Move arquivo para local seguro com nome sanitizado
	 */
	public function moveToSecureLocation(string $uploadDirectory, string $customName = null): string
	{
		if (!$this->validate()) {
			throw new ValidationException('Validação de imagem falhou', $this->errors);
		}

		// Gera nome seguro se não fornecido
		if ($customName === null) {
			$extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
			$customName = uniqid('img_', true) . '.' . $extension;
		}

		$targetPath = rtrim($uploadDirectory, '/') . '/' . basename($customName);

		// Cria diretório se não existe
		if (!is_dir($uploadDirectory)) {
			if (!mkdir($uploadDirectory, 0755, true)) {
				throw new ValidationException('Não foi possível criar diretório de upload');
			}
		}

		// Move arquivo
		if (!move_uploaded_file($this->file['tmp_name'], $targetPath)) {
			throw new ValidationException('Falha ao mover arquivo para destino final');
		}

		// Define permissões seguras
		chmod($targetPath, 0644);

		return $targetPath;
	}

	/**
	 * Retorna informações da imagem após validação
	 */
	public function getImageInfo(): array
	{
		if (!$this->validate()) {
			return [];
		}

		$imageInfo = getimagesize($this->file['tmp_name']);

		return [
			'width' => $imageInfo[0],
			'height' => $imageInfo[1],
			'type' => $imageInfo[2],
			'mime' => $imageInfo['mime'],
			'filesize' => $this->file['size'],
			'original_name' => $this->file['name'],
			'aspect_ratio' => round($imageInfo[0] / $imageInfo[1], 2)
		];
	}

	/**
	 * Valida múltiplas imagens de uma vez
	 */
	public static function validateMultiple(array $files, array $config = []): array
	{
		$results = [];

		foreach ($files['tmp_name'] as $key => $tmpName) {
			$file = [
				'name' => $files['name'][$key],
				'type' => $files['type'][$key],
				'tmp_name' => $tmpName,
				'error' => $files['error'][$key],
				'size' => $files['size'][$key]
			];

			$validator = new self($file, $config);

			$results[$key] = [
				'valid' => $validator->validate(),
				'errors' => $validator->getErrors(),
				'info' => $validator->valid ? $validator->getImageInfo() : []
			];
		}

		return $results;
	}
}
