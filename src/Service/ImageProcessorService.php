<?php

declare(strict_types=1);

namespace Src\Service;

use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Src\Exception\ImageProcessingException;

/**
 * Serviço para processamento de imagens usando Intervention Image
 * Suporta redimensionamento, otimização e geração de thumbnails
 */
class ImageProcessorService
{
	private ImageManager $manager;
	private array $config;

	public function __construct(array $config = [])
	{
		$this->config = array_merge([
			'driver' => 'gd', // gd ou imagick
			'quality' => 80,
			'max_width' => 1920,
			'max_height' => 1080,
			'thumbnail_sizes' => [
				'small' => [150, 150],
				'medium' => [300, 300],
				'large' => [800, 600]
			]
		], $config);

		$this->initializeDriver();
	}

	private function initializeDriver(): void
	{
		try {
			$driver = $this->config['driver'] === 'imagick' && extension_loaded('imagick')
				? new ImagickDriver()
				: new GdDriver();

			$this->manager = new ImageManager($driver);
		} catch (\Exception $e) {
			throw new ImageProcessingException('Falha ao inicializar driver de imagem: ' . $e->getMessage());
		}
	}

	/**
	 * Processa upload completo com otimização e geração de thumbnails
	 */
	public function processUpload(string $sourcePath, string $basePath, string $filename): array
	{
		$results = [];

		try {
			// Carrega a imagem
			$image = $this->manager->read($sourcePath);

			// Salva versão otimizada original
			$originalPath = $basePath . '/original/' . $filename;
			$this->ensureDirectoryExists(dirname($originalPath));
			$results['original'] = $this->saveOptimizedImage($image, $originalPath);

			// Gera thumbnails
			foreach ($this->config['thumbnail_sizes'] as $size => $dimensions) {
				$thumbnailPath = $basePath . '/' . $size . '/' . $filename;
				$this->ensureDirectoryExists(dirname($thumbnailPath));
				$results[$size] = $this->generateThumbnail($image, $thumbnailPath, $dimensions[0], $dimensions[1]);
			}

			return $results;
		} catch (\Exception $e) {
			throw new ImageProcessingException('Erro ao processar imagem: ' . $e->getMessage());
		}
	}

	/**
	 * Salva imagem otimizada mantendo qualidade e reduzindo tamanho
	 */
	public function saveOptimizedImage(ImageInterface $image, string $targetPath): array
	{
		// Redimensiona se necessário mantendo proporção
		if ($image->width() > $this->config['max_width'] || $image->height() > $this->config['max_height']) {
			$image = $image->scaleDown($this->config['max_width'], $this->config['max_height']);
		}

		// Determina formato baseado na extensão
		$extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
		$format = $this->getImageFormat($extension);

		// Aplica compressão baseada no formato
		if ($format === 'jpeg') {
			$image = $image->toJpeg($this->config['quality']);
		} elseif ($format === 'png') {
			$image = $image->toPng();
		} elseif ($format === 'webp' && $this->supportsWebP()) {
			$image = $image->toWebp($this->config['quality']);
		}

		$image->save($targetPath);

		return [
			'path' => $targetPath,
			'size' => filesize($targetPath),
			'dimensions' => [
				'width' => $image->width(),
				'height' => $image->height()
			]
		];
	}

	/**
	 * Gera thumbnail com dimensões específicas
	 */
	public function generateThumbnail(ImageInterface $image, string $targetPath, int $width, int $height): array
	{
		$thumbnail = clone $image;

		// Redimensiona mantendo proporção e cortando se necessário
		$thumbnail = $thumbnail->cover($width, $height);

		$extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
		$format = $this->getImageFormat($extension);

		if ($format === 'jpeg') {
			$thumbnail = $thumbnail->toJpeg($this->config['quality']);
		} elseif ($format === 'png') {
			$thumbnail = $thumbnail->toPng();
		}

		$thumbnail->save($targetPath);

		return [
			'path' => $targetPath,
			'size' => filesize($targetPath),
			'dimensions' => ['width' => $width, 'height' => $height]
		];
	}

	/**
	 * Converte formato de imagem se necessário
	 */
	public function convertFormat(string $sourcePath, string $targetPath, string $targetFormat): array
	{
		try {
			$image = $this->manager->read($sourcePath);

			switch (strtolower($targetFormat)) {
				case 'jpeg':
				case 'jpg':
					$image = $image->toJpeg($this->config['quality']);
					break;
				case 'png':
					$image = $image->toPng();
					break;
				case 'webp':
					if (!$this->supportsWebP()) {
						throw new ImageProcessingException('WebP não é suportado neste servidor');
					}
					$image = $image->toWebp($this->config['quality']);
					break;
				default:
					throw new ImageProcessingException('Formato não suportado: ' . $targetFormat);
			}

			$image->save($targetPath);

			return [
				'path' => $targetPath,
				'size' => filesize($targetPath),
				'format' => $targetFormat
			];
		} catch (\Exception $e) {
			throw new ImageProcessingException('Erro na conversão de formato: ' . $e->getMessage());
		}
	}

	/**
	 * Analisa imagem e retorna metadados
	 */
	public function analyzeImage(string $imagePath): array
	{
		try {
			$image = $this->manager->read($imagePath);

			return [
				'width' => $image->width(),
				'height' => $image->height(),
				'filesize' => filesize($imagePath),
				'mime_type' => mime_content_type($imagePath),
				'extension' => strtolower(pathinfo($imagePath, PATHINFO_EXTENSION)),
				'aspect_ratio' => round($image->width() / $image->height(), 2)
			];
		} catch (\Exception $e) {
			throw new ImageProcessingException('Erro ao analisar imagem: ' . $e->getMessage());
		}
	}

	/**
	 * Verifica se formato WebP é suportado
	 */
	public function supportsWebP(): bool
	{
		if ($this->config['driver'] === 'imagick' && extension_loaded('imagick')) {
			return in_array('WEBP', \Imagick::queryFormats());
		}

		return function_exists('imagewebp');
	}

	/**
	 * Otimiza tamanho do arquivo sem perder qualidade significativa
	 */
	public function optimizeFileSize(string $imagePath, int $targetSizeKB): string
	{
		$image = $this->manager->read($imagePath);
		$quality = $this->config['quality'];

		// Reduz qualidade gradualmente até atingir tamanho desejado
		while ($quality > 30) {
			$tempPath = $imagePath . '.temp';
			$image->toJpeg($quality)->save($tempPath);

			$sizeKB = filesize($tempPath) / 1024;

			if ($sizeKB <= $targetSizeKB) {
				rename($tempPath, $imagePath);
				return $imagePath;
			}

			$quality -= 10;
			unlink($tempPath);
		}

		throw new ImageProcessingException('Não foi possível otimizar a imagem para o tamanho desejado');
	}

	private function getImageFormat(string $extension): string
	{
		return match ($extension) {
			'jpg', 'jpeg' => 'jpeg',
			'png' => 'png',
			'webp' => 'webp',
			'gif' => 'gif',
			default => 'jpeg'
		};
	}

	private function ensureDirectoryExists(string $directory): void
	{
		if (!is_dir($directory)) {
			mkdir($directory, 0755, true);
		}
	}

	/**
	 * Remove todas as versões de uma imagem
	 */
	public function removeImageVersions(string $basePath, string $filename): bool
	{
		$removed = true;

		// Remove original
		$originalPath = $basePath . '/original/' . $filename;
		if (file_exists($originalPath) && !unlink($originalPath)) {
			$removed = false;
		}

		// Remove thumbnails
		foreach ($this->config['thumbnail_sizes'] as $size => $dimensions) {
			$thumbnailPath = $basePath . '/' . $size . '/' . $filename;
			if (file_exists($thumbnailPath) && !unlink($thumbnailPath)) {
				$removed = false;
			}
		}

		return $removed;
	}
}
