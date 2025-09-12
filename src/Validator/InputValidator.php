<?php

declare(strict_types=1);

namespace Src\Validator;

/**
 * Input validation framework with comprehensive validation rules
 */
class InputValidator
{
	private array $errors = [];
	private array $data = [];
	private array $rules = [];

	/**
	 * Create validator instance with data to validate
	 */
	public function __construct(array $data)
	{
		$this->data = $data;
	}

	/**
	 * Static factory method
	 */
	public static function make(array $data): self
	{
		return new self($data);
	}

	/**
	 * Validate data against rules
	 *
	 * @param array $rules Validation rules
	 * @return bool True if validation passes
	 */
	public function validate(array $rules): bool
	{
		$this->rules = $rules;
		$this->errors = [];

		foreach ($rules as $field => $fieldRules) {
			$this->validateField($field, $fieldRules);
		}

		return empty($this->errors);
	}

	/**
	 * Get validation errors
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * Get first error message
	 */
	public function getFirstError(): ?string
	{
		if (empty($this->errors)) {
			return null;
		}

		$firstField = array_key_first($this->errors);
		return $this->errors[$firstField][0] ?? null;
	}

	/**
	 * Get validated data (only fields that passed validation)
	 */
	public function getValidated(): array
	{
		$validated = [];

		foreach ($this->rules as $field => $rules) {
			if (!isset($this->errors[$field]) && isset($this->data[$field])) {
				$validated[$field] = $this->sanitize($this->data[$field]);
			}
		}

		return $validated;
	}

	/**
	 * Validate a single field
	 */
	private function validateField(string $field, $rules): void
	{
		if (is_string($rules)) {
			$rules = explode('|', $rules);
		}

		$value = $this->data[$field] ?? null;

		foreach ($rules as $rule) {
			$this->applyRule($field, $value, $rule);
		}
	}

	/**
	 * Apply a validation rule
	 */
	private function applyRule(string $field, $value, string $rule): void
	{
		$params = [];

		if (strpos($rule, ':') !== false) {
			[$rule, $paramString] = explode(':', $rule, 2);
			$params = explode(',', $paramString);
		}

		switch ($rule) {
			case 'required':
				if ($value === null || $value === '' || $value === []) {
					$this->addError($field, "O campo $field é obrigatório.");
				}
				break;

			case 'email':
				if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
					$this->addError($field, "O campo $field deve ser um email válido.");
				}
				break;

			case 'numeric':
				if ($value && !is_numeric($value)) {
					$this->addError($field, "O campo $field deve ser numérico.");
				}
				break;

			case 'integer':
				if ($value && !filter_var($value, FILTER_VALIDATE_INT)) {
					$this->addError($field, "O campo $field deve ser um número inteiro.");
				}
				break;

			case 'min':
				$min = $params[0] ?? 0;
				if (is_string($value) && strlen($value) < $min) {
					$this->addError($field, "O campo $field deve ter no mínimo $min caracteres.");
				} elseif (is_numeric($value) && $value < $min) {
					$this->addError($field, "O campo $field deve ser no mínimo $min.");
				}
				break;

			case 'max':
				$max = $params[0] ?? PHP_INT_MAX;
				if (is_string($value) && strlen($value) > $max) {
					$this->addError($field, "O campo $field deve ter no máximo $max caracteres.");
				} elseif (is_numeric($value) && $value > $max) {
					$this->addError($field, "O campo $field deve ser no máximo $max.");
				}
				break;

			case 'between':
				$min = $params[0] ?? 0;
				$max = $params[1] ?? PHP_INT_MAX;
				if (is_numeric($value) && ($value < $min || $value > $max)) {
					$this->addError($field, "O campo $field deve estar entre $min e $max.");
				}
				break;

			case 'in':
				if ($value && !in_array($value, $params)) {
					$this->addError($field, "O campo $field deve ser um dos valores: " . implode(', ', $params));
				}
				break;

			case 'date':
				if ($value && !$this->isValidDate($value)) {
					$this->addError($field, "O campo $field deve ser uma data válida.");
				}
				break;

			case 'url':
				if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
					$this->addError($field, "O campo $field deve ser uma URL válida.");
				}
				break;

			case 'regex':
				$pattern = $params[0] ?? '';
				if ($value && !preg_match($pattern, $value)) {
					$this->addError($field, "O campo $field tem formato inválido.");
				}
				break;

			case 'alphanumeric':
				if ($value && !ctype_alnum($value)) {
					$this->addError($field, "O campo $field deve conter apenas letras e números.");
				}
				break;

			case 'phone':
				if ($value && !$this->isValidPhone($value)) {
					$this->addError($field, "O campo $field deve ser um telefone válido.");
				}
				break;

			case 'cpf':
				if ($value && !$this->isValidCPF($value)) {
					$this->addError($field, "O campo $field deve ser um CPF válido.");
				}
				break;

			case 'cnpj':
				if ($value && !$this->isValidCNPJ($value)) {
					$this->addError($field, "O campo $field deve ser um CNPJ válido.");
				}
				break;
		}
	}

	/**
	 * Add error message for field
	 */
	private function addError(string $field, string $message): void
	{
		if (!isset($this->errors[$field])) {
			$this->errors[$field] = [];
		}

		$this->errors[$field][] = $message;
	}

	/**
	 * Check if date is valid
	 */
	private function isValidDate(string $date): bool
	{
		$formats = ['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s'];

		foreach ($formats as $format) {
			$d = \DateTime::createFromFormat($format, $date);
			if ($d && $d->format($format) === $date) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate Brazilian phone number
	 */
	private function isValidPhone(string $phone): bool
	{
		$phone = preg_replace('/[^0-9]/', '', $phone);
		return strlen($phone) === 10 || strlen($phone) === 11;
	}

	/**
	 * Validate Brazilian CPF
	 */
	private function isValidCPF(string $cpf): bool
	{
		$cpf = preg_replace('/[^0-9]/', '', $cpf);

		if (strlen($cpf) !== 11) {
			return false;
		}

		// Check for known invalid CPFs
		if (preg_match('/^(\d)\1{10}$/', $cpf)) {
			return false;
		}

		// Validate check digits
		for ($t = 9; $t < 11; $t++) {
			$d = 0;
			for ($c = 0; $c < $t; $c++) {
				$d += $cpf[$c] * (($t + 1) - $c);
			}
			$d = ((10 * $d) % 11) % 10;
			if ($cpf[$c] != $d) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate Brazilian CNPJ
	 */
	private function isValidCNPJ(string $cnpj): bool
	{
		$cnpj = preg_replace('/[^0-9]/', '', $cnpj);

		if (strlen($cnpj) !== 14) {
			return false;
		}

		// Check for known invalid CNPJs
		if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
			return false;
		}

		// Validate check digits
		$length = strlen($cnpj) - 2;
		$numbers = substr($cnpj, 0, $length);
		$digits = substr($cnpj, $length);

		$sum = 0;
		$pos = $length - 7;

		for ($i = $length; $i >= 1; $i--) {
			$sum += $numbers[$length - $i] * $pos--;
			if ($pos < 2) {
				$pos = 9;
			}
		}

		$result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

		if ($result != $digits[0]) {
			return false;
		}

		$length++;
		$numbers = substr($cnpj, 0, $length);
		$sum = 0;
		$pos = $length - 7;

		for ($i = $length; $i >= 1; $i--) {
			$sum += $numbers[$length - $i] * $pos--;
			if ($pos < 2) {
				$pos = 9;
			}
		}

		$result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

		return $result == $digits[1];
	}

	/**
	 * Sanitize input value
	 */
	private function sanitize($value)
	{
		if (is_string($value)) {
			return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
		}

		if (is_array($value)) {
			return array_map([$this, 'sanitize'], $value);
		}

		return $value;
	}
}
