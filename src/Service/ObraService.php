<?php

declare(strict_types=1);

namespace Src\Service;

use Src\Repository\ObraRepository;
use Src\Repository\EmpresaRepository;
use Src\Repository\DiarioObraRepository;
use Src\Validator\InputValidator;
use Src\Exception\ServiceException;
use Src\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Business logic service for Obra (Project) management
 */
class ObraService
{
	private ObraRepository $obraRepository;
	private EmpresaRepository $empresaRepository;
	private DiarioObraRepository $diarioObraRepository;
	private LoggerInterface $logger;

	public function __construct(
		ObraRepository $obraRepository,
		EmpresaRepository $empresaRepository,
		DiarioObraRepository $diarioObraRepository,
		?LoggerInterface $logger = null
	) {
		$this->obraRepository = $obraRepository;
		$this->empresaRepository = $empresaRepository;
		$this->diarioObraRepository = $diarioObraRepository;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * Create a new project
	 *
	 * @throws ValidationException
	 * @throws ServiceException
	 */
	public function createProject(array $data): array
	{
		$this->logger->info('Creating new project', ['data' => $data]);

		// Validate input
		$validator = InputValidator::make($data);
		if (!$validator->validate([
			'fk_id_contratante' => 'required|integer|min:1',
			'fk_id_contratada' => 'required|integer|min:1',
			'descricao_resumo' => 'required|min:3|max:500'
		])) {
			throw new ValidationException(
				'Validation failed: ' . $validator->getFirstError(),
				$validator->getErrors()
			);
		}

		$validatedData = $validator->getValidated();

		// Verify companies exist
		$contratante = $this->empresaRepository->find($validatedData['fk_id_contratante']);
		if (!$contratante) {
			throw new ServiceException('Empresa contratante não encontrada');
		}

		$contratada = $this->empresaRepository->find($validatedData['fk_id_contratada']);
		if (!$contratada) {
			throw new ServiceException('Empresa contratada não encontrada');
		}

		// Verify contratante is actually a contractor
		if (!$contratante['contratante_sn']) {
			throw new ServiceException('Empresa selecionada não é uma contratante');
		}

		// Verify contratada is not a contractor
		if ($contratada['contratante_sn']) {
			throw new ServiceException('Empresa selecionada não pode ser contratada');
		}

		// Check for duplicate projects
		$existing = $this->obraRepository->findOneWhere([
			'fk_id_contratante' => $validatedData['fk_id_contratante'],
			'fk_id_contratada' => $validatedData['fk_id_contratada'],
			'descricao_resumo' => $validatedData['descricao_resumo']
		]);

		if ($existing) {
			throw new ServiceException('Projeto já existe com estas especificações');
		}

		try {
			// Create project in transaction
			$projectId = $this->obraRepository->transaction(function () use ($validatedData) {
				return $this->obraRepository->create($validatedData);
			});

			$this->logger->info('Project created successfully', ['project_id' => $projectId]);

			// Return created project with relations
			$project = $this->obraRepository->findWithRelations($projectId);

			if (!$project) {
				throw new ServiceException('Failed to retrieve created project');
			}

			return $project;
		} catch (\Exception $e) {
			$this->logger->error('Failed to create project', [
				'error' => $e->getMessage(),
				'data' => $validatedData
			]);

			throw new ServiceException(
				'Erro ao criar projeto: ' . $e->getMessage(),
				0,
				$e
			);
		}
	}

	/**
	 * Update existing project
	 *
	 * @throws ValidationException
	 * @throws ServiceException
	 */
	public function updateProject(int $projectId, array $data): array
	{
		$this->logger->info('Updating project', ['project_id' => $projectId, 'data' => $data]);

		// Check if project exists
		$project = $this->obraRepository->find($projectId);
		if (!$project) {
			throw new ServiceException('Projeto não encontrado');
		}

		// Validate input
		$validator = InputValidator::make($data);
		$rules = [];

		if (isset($data['fk_id_contratante'])) {
			$rules['fk_id_contratante'] = 'integer|min:1';
		}

		if (isset($data['fk_id_contratada'])) {
			$rules['fk_id_contratada'] = 'integer|min:1';
		}

		if (isset($data['descricao_resumo'])) {
			$rules['descricao_resumo'] = 'min:3|max:500';
		}

		if (!empty($rules) && !$validator->validate($rules)) {
			throw new ValidationException(
				'Validation failed: ' . $validator->getFirstError(),
				$validator->getErrors()
			);
		}

		$validatedData = $validator->getValidated();

		// Verify new companies if changed
		if (isset($validatedData['fk_id_contratante'])) {
			$contratante = $this->empresaRepository->find($validatedData['fk_id_contratante']);
			if (!$contratante || !$contratante['contratante_sn']) {
				throw new ServiceException('Empresa contratante inválida');
			}
		}

		if (isset($validatedData['fk_id_contratada'])) {
			$contratada = $this->empresaRepository->find($validatedData['fk_id_contratada']);
			if (!$contratada || $contratada['contratante_sn']) {
				throw new ServiceException('Empresa contratada inválida');
			}
		}

		try {
			// Update project
			$success = $this->obraRepository->update($projectId, $validatedData);

			if (!$success) {
				throw new ServiceException('Failed to update project');
			}

			$this->logger->info('Project updated successfully', ['project_id' => $projectId]);

			// Return updated project
			return $this->obraRepository->findWithRelations($projectId);
		} catch (\Exception $e) {
			$this->logger->error('Failed to update project', [
				'project_id' => $projectId,
				'error' => $e->getMessage()
			]);

			throw new ServiceException(
				'Erro ao atualizar projeto: ' . $e->getMessage(),
				0,
				$e
			);
		}
	}

	/**
	 * Delete project and all related data
	 *
	 * @throws ServiceException
	 */
	public function deleteProject(int $projectId): bool
	{
		$this->logger->info('Deleting project', ['project_id' => $projectId]);

		// Check if project exists
		$project = $this->obraRepository->find($projectId);
		if (!$project) {
			throw new ServiceException('Projeto não encontrado');
		}

		try {
			return $this->obraRepository->transaction(function () use ($projectId) {
				// Delete all diary entries for this project
				$diaries = $this->diarioObraRepository->findByProject($projectId);

				foreach ($diaries as $diary) {
					// Delete diary photos
					$this->deleteDiaryPhotos($diary['id_diario_obra']);

					// Delete diary employee records
					$this->diarioObraRepository->deleteEmployeeRecords($diary['id_diario_obra']);

					// Delete diary
					$this->diarioObraRepository->delete($diary['id_diario_obra']);
				}

				// Delete project
				$success = $this->obraRepository->delete($projectId);

				$this->logger->info('Project deleted successfully', ['project_id' => $projectId]);

				return $success;
			});
		} catch (\Exception $e) {
			$this->logger->error('Failed to delete project', [
				'project_id' => $projectId,
				'error' => $e->getMessage()
			]);

			throw new ServiceException(
				'Erro ao deletar projeto: ' . $e->getMessage(),
				0,
				$e
			);
		}
	}

	/**
	 * Get project details with statistics
	 */
	public function getProjectDetails(int $projectId): array
	{
		$project = $this->obraRepository->findWithRelations($projectId);

		if (!$project) {
			throw new ServiceException('Projeto não encontrado');
		}

		// Add statistics
		$project['statistics'] = $this->obraRepository->getProjectStatistics($projectId);

		// Add recent activities
		$project['recent_activities'] = $this->diarioObraRepository->getRecentByProject($projectId, 5);

		return $project;
	}

	/**
	 * List projects with filters and pagination
	 */
	public function listProjects(array $filters = [], int $page = 1, int $perPage = 20): array
	{
		// Validate pagination parameters
		$page = max(1, $page);
		$perPage = min(100, max(1, $perPage));

		return $this->obraRepository->getPaginated($filters, $page, $perPage);
	}

	/**
	 * Get active projects
	 */
	public function getActiveProjects(int $daysBack = 30): array
	{
		return $this->obraRepository->getActiveProjects($daysBack);
	}

	/**
	 * Search projects by description
	 */
	public function searchProjects(string $query): array
	{
		if (strlen($query) < 2) {
			return [];
		}

		return $this->obraRepository->searchByDescription($query);
	}

	/**
	 * Get project summary for dashboard
	 */
	public function getProjectSummary(): array
	{
		$totalProjects = $this->obraRepository->count();
		$activeProjects = count($this->obraRepository->getActiveProjects(30));
		$inactiveProjects = $totalProjects - $activeProjects;

		return [
			'total' => $totalProjects,
			'active' => $activeProjects,
			'inactive' => $inactiveProjects,
			'recent' => $this->obraRepository->findAll(5, 0)
		];
	}

	/**
	 * Delete diary photos
	 */
	private function deleteDiaryPhotos(int $diaryId): void
	{
		$photos = $this->diarioObraRepository->getPhotos($diaryId);

		foreach ($photos as $photo) {
			if (file_exists($photo['url'])) {
				unlink($photo['url']);
			}
		}

		$this->diarioObraRepository->deletePhotos($diaryId);
	}
}
