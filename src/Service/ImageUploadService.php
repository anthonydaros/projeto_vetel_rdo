<?php

declare(strict_types=1);

namespace Src\Service;

use Src\Validator\ImageUploadValidator;
use Src\Repository\ImageRepository;
use Src\Exception\ValidationException;
use Src\Exception\ImageProcessingException;
use Src\Exception\ServiceException;

/**
 * Serviço principal para gerenciamento de upload de imagens
 * Coordena validação, processamento e armazenamento
 */
class ImageUploadService
{
	private ImageProcessorService $processor;
	private ImageRepository $repository;
	private array $config;

	public function __construct(
		ImageProcessorService $processor,
		ImageRepository $repository,
		array $config = []
	) {
		$this->processor = $processor;
		$this->repository = $repository;
		$this->config = array_merge([
			'base_path' => __DIR__ . '/../../img/album',
			'max_files_per_diario' => 20,
			'generate_thumbnails' => true,
			'optimize_images' => true,
			'allowed_formats' => ['jpg', 'jpeg', 'png', 'webp'],
			'max_size' => 10 * 1024 * 1024, // 10MB
		], $config);
	}

	/**
	 * Processa upload de imagem para um diário de obra
	 */
	public function uploadImageForDiario(array $file, int $diarioId): array
	{
		try {
			// Validação inicial
			$validator = new ImageUploadValidator($file, [
				'max_size' => $this->config['max_size'],
				'allowed_types' => $this->getMimeTypesFromFormats(),
				'allowed_extensions' => $this->config['allowed_formats']
			]);

			if (!$validator->validate()) {
				throw new ValidationException('Validação de imagem falhou', $validator->getErrors());
			}

			// Verifica limite de imagens por diário
			$currentCount = $this->repository->countImagesByDiario($diarioId);
			if ($currentCount >= $this->config['max_files_per_diario']) {
				throw new ServiceException("Limite de {$this->config['max_files_per_diario']} imagens por diário atingido");
			}

			// Gera nome único para a imagem
			$imageIndex = $currentCount;
			$filename = "diario-{$diarioId}-foto-{$imageIndex}.jpg";
			$basePath = $this->config['base_path'];

			// Move arquivo para local temporário
			$tempPath = $validator->moveToSecureLocation(sys_get_temp_dir(), 'temp_' . $filename);

			try {
				// Processa imagem (otimização + thumbnails)
				$processedImages = $this->processor->processUpload($tempPath, $basePath, $filename);

				// Salva registro no banco
				$imageRecord = $this->repository->create([
					'fk_id_diario_obra' => $diarioId,
					'filename' => $filename,
					'original_name' => $file['name'],
					'file_size' => $processedImages['original']['size'],
					'width' => $processedImages['original']['dimensions']['width'],
					'height' => $processedImages['original']['dimensions']['height'],
					'mime_type' => mime_content_type($processedImages['original']['path']),
					'uploaded_at' => date('Y-m-d H:i:s')
				]);

				// Remove arquivo temporário
				unlink($tempPath);

				return [
					'success' => true,
					'image_id' => $imageRecord['id'],
					'filename' => $filename,
					'paths' => $processedImages,
					'info' => $validator->getImageInfo()
				];
			} catch (ImageProcessingException $e) {
				// Remove arquivo temporário em caso de erro
				if (file_exists($tempPath)) {
					unlink($tempPath);
				}
				throw $e;
			}
		} catch (\Exception $e) {
			throw new ServiceException(
				'Falha no upload da imagem: ' . $e->getMessage(),
				0,
				$e
			);
		}
	}

	/**
	 * Upload múltiplo de imagens
	 */
	public function uploadMultipleImages(array $files, int $diarioId): array
	{
		$results = [];
		$errors = [];

		// Valida todas as imagens primeiro
		$validationResults = ImageUploadValidator::validateMultiple($files, [
			'max_size' => $this->config['max_size'],
			'allowed_types' => $this->getMimeTypesFromFormats(),
			'allowed_extensions' => $this->config['allowed_formats']
		]);

		$validFiles = array_filter($validationResults, fn ($result) => $result['valid']);

		// Verifica limite total
		$currentCount = $this->repository->countImagesByDiario($diarioId);
		if ($currentCount + count($validFiles) > $this->config['max_files_per_diario']) {
			throw new ServiceException(
				'Upload resultaria em ' . ($currentCount + count($validFiles)) .
				" imagens. Limite máximo: {$this->config['max_files_per_diario']}"
			);
		}

		// Processa cada arquivo válido
		foreach ($validFiles as $key => $validation) {
			try {
				$file = [
					'name' => $files['name'][$key],
					'type' => $files['type'][$key],
					'tmp_name' => $files['tmp_name'][$key],
					'error' => $files['error'][$key],
					'size' => $files['size'][$key]
				];

				$result = $this->uploadImageForDiario($file, $diarioId);
				$results[] = $result;
			} catch (\Exception $e) {
				$errors[] = [
					'file' => $files['name'][$key],
					'error' => $e->getMessage()
				];
			}
		}

		// Adiciona erros de validação
		foreach ($validationResults as $key => $validation) {
			if (!$validation['valid']) {
				$errors[] = [
					'file' => $files['name'][$key],
					'error' => implode(', ', $validation['errors'])
				];
			}
		}

		return [
			'success_count' => count($results),
			'error_count' => count($errors),
			'results' => $results,
			'errors' => $errors
		];
	}

	/**
	 * Remove imagem e todas suas versões
	 */
	public function deleteImage(int $imageId): bool
	{
		try {
			$image = $this->repository->findById($imageId);

			if (!$image) {
				throw new ServiceException('Imagem não encontrada');
			}

			// Remove arquivos físicos
			$basePath = $this->config['base_path'];
			$removed = $this->processor->removeImageVersions($basePath, $image['filename']);

			// Remove registro do banco
			$this->repository->delete($imageId);

			return $removed;
		} catch (\Exception $e) {
			throw new ServiceException('Falha ao remover imagem: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Remove todas as imagens de um diário
	 */
	public function deleteAllImagesFromDiario(int $diarioId): array
	{
		$images = $this->repository->findByDiario($diarioId);
		$results = [];

		foreach ($images as $image) {
			try {
				$this->deleteImage($image['id']);
				$results['success'][] = $image['filename'];
			} catch (\Exception $e) {
				$results['errors'][] = [
					'filename' => $image['filename'],
					'error' => $e->getMessage()
				];
			}
		}

		return $results;
	}

	/**
	 * Lista imagens de um diário com URLs
	 */
	public function getImagesFromDiario(int $diarioId): array
	{
		$images = $this->repository->findByDiario($diarioId);

		foreach ($images as &$image) {
			$image['urls'] = $this->generateImageUrls($image['filename']);
		}

		return $images;
	}

	/**
	 * Otimiza imagem existente
	 */
	public function optimizeExistingImage(int $imageId, array $options = []): array
	{
		try {
			$image = $this->repository->findById($imageId);

			if (!$image) {
				throw new ServiceException('Imagem não encontrada');
			}

			$basePath = $this->config['base_path'];
			$originalPath = $basePath . '/original/' . $image['filename'];

			if (!file_exists($originalPath)) {
				throw new ServiceException('Arquivo original não encontrado');
			}

			// Reprocessa a imagem
			$processedImages = $this->processor->processUpload($originalPath, $basePath, $image['filename']);

			// Atualiza informações no banco
			$this->repository->update($imageId, [
				'file_size' => $processedImages['original']['size'],
				'width' => $processedImages['original']['dimensions']['width'],
				'height' => $processedImages['original']['dimensions']['height'],
				'updated_at' => date('Y-m-d H:i:s')
			]);

			return $processedImages;
		} catch (\Exception $e) {
			throw new ServiceException('Falha na otimização: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Gera URLs para diferentes versões da imagem
	 */
	private function generateImageUrls(string $filename): array
	{
		$baseUrl = '/img/album';

		return [
			'original' => $baseUrl . '/original/' . $filename,
			'small' => $baseUrl . '/small/' . $filename,
			'medium' => $baseUrl . '/medium/' . $filename,
			'large' => $baseUrl . '/large/' . $filename
		];
	}

	/**
	 * Converte formatos para tipos MIME
	 */
	private function getMimeTypesFromFormats(): array
	{
		$mimeMap = [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp'
		];

		$mimeTypes = [];
		foreach ($this->config['allowed_formats'] as $format) {
			if (isset($mimeMap[$format])) {
				$mimeTypes[] = $mimeMap[$format];
			}
		}

		return array_unique($mimeTypes);
	}

	/**
	 * Obtém estatísticas de upload
	 */
	public function getUploadStats(int $diarioId = null): array
	{
		return [
			'total_images' => $this->repository->getTotalCount($diarioId),
			'total_size' => $this->repository->getTotalSize($diarioId),
			'images_by_diario' => $diarioId ? null : $this->repository->getCountByDiario(),
			'recent_uploads' => $this->repository->getRecentUploads(10)
		];
	}
}
