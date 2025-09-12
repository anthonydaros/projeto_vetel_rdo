<?php

declare(strict_types=1);

namespace Src\Exception;

/**
 * Base exception for application-specific exceptions
 */
abstract class BaseException extends \Exception
{
	protected array $context = [];
	protected string $userMessage = '';
	protected int $httpStatusCode = 500;

	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		array $context = []
	) {
		parent::__construct($message, $code, $previous);
		$this->context = $context;

		if (empty($this->userMessage)) {
			$this->userMessage = $message;
		}
	}

	/**
	 * Get exception context data
	 */
	public function getContext(): array
	{
		return $this->context;
	}

	/**
	 * Get user-friendly error message
	 */
	public function getUserMessage(): string
	{
		return $this->userMessage;
	}

	/**
	 * Set user-friendly error message
	 */
	public function setUserMessage(string $message): self
	{
		$this->userMessage = $message;
		return $this;
	}

	/**
	 * Get HTTP status code for this exception
	 */
	public function getHttpStatusCode(): int
	{
		return $this->httpStatusCode;
	}

	/**
	 * Set HTTP status code
	 */
	public function setHttpStatusCode(int $code): self
	{
		$this->httpStatusCode = $code;
		return $this;
	}

	/**
	 * Convert exception to array for logging
	 */
	public function toArray(): array
	{
		return [
			'exception' => get_class($this),
			'message' => $this->getMessage(),
			'code' => $this->getCode(),
			'file' => $this->getFile(),
			'line' => $this->getLine(),
			'context' => $this->context,
			'trace' => $this->getTraceAsString()
		];
	}

	/**
	 * Convert exception to JSON
	 */
	public function toJson(): string
	{
		return json_encode($this->toArray());
	}
}

/**
 * Validation exception
 */
class ValidationException extends BaseException
{
	protected int $httpStatusCode = 400;
	private array $errors = [];

	public function __construct(
		string $message = 'Validation failed',
		array $errors = [],
		int $code = 0,
		?\Throwable $previous = null
	) {
		$this->errors = $errors;
		parent::__construct($message, $code, $previous, ['errors' => $errors]);
		$this->userMessage = 'Dados inválidos fornecidos';
	}

	/**
	 * Get validation errors
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}
}

/**
 * Service layer exception
 */
class ServiceException extends BaseException
{
	protected int $httpStatusCode = 500;

	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		array $context = []
	) {
		parent::__construct($message, $code, $previous, $context);
		$this->userMessage = 'Erro ao processar solicitação';
	}
}

/**
 * Repository exception
 */
class RepositoryException extends BaseException
{
	protected int $httpStatusCode = 500;

	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		array $context = []
	) {
		parent::__construct($message, $code, $previous, $context);
		$this->userMessage = 'Erro ao acessar dados';
	}
}

/**
 * Not found exception
 */
class NotFoundException extends BaseException
{
	protected int $httpStatusCode = 404;

	public function __construct(
		string $resource = 'Resource',
		int $code = 0,
		?\Throwable $previous = null
	) {
		$message = "$resource not found";
		parent::__construct($message, $code, $previous);
		$this->userMessage = "$resource não encontrado";
	}
}

/**
 * Authorization exception
 */
class UnauthorizedException extends BaseException
{
	protected int $httpStatusCode = 401;

	public function __construct(
		string $message = 'Unauthorized',
		int $code = 0,
		?\Throwable $previous = null
	) {
		parent::__construct($message, $code, $previous);
		$this->userMessage = 'Não autorizado';
	}
}

/**
 * Access denied exception
 */
class ForbiddenException extends BaseException
{
	protected int $httpStatusCode = 403;

	public function __construct(
		string $message = 'Access denied',
		int $code = 0,
		?\Throwable $previous = null
	) {
		parent::__construct($message, $code, $previous);
		$this->userMessage = 'Acesso negado';
	}
}

/**
 * Configuration exception
 */
class ConfigurationException extends BaseException
{
	protected int $httpStatusCode = 500;

	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null
	) {
		parent::__construct($message, $code, $previous);
		$this->userMessage = 'Erro de configuração do sistema';
	}
}

/**
 * File system exception
 */
class FileSystemException extends BaseException
{
	protected int $httpStatusCode = 500;

	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		array $context = []
	) {
		parent::__construct($message, $code, $previous, $context);
		$this->userMessage = 'Erro ao manipular arquivo';
	}
}
