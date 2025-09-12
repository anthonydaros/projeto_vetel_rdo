<?php
declare(strict_types=1);

namespace Src\Validator;

/**
 * Secure file upload validation
 */
class FileUploadValidator
{
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    private const ALLOWED_DOCUMENT_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    private const MAX_FILE_SIZE = 10485760; // 10MB in bytes
    
    private array $errors = [];
    private array $file;
    private array $config;
    
    public function __construct(array $file, array $config = [])
    {
        $this->file = $file;
        $this->config = array_merge([
            'max_size' => self::MAX_FILE_SIZE,
            'allowed_types' => array_merge(self::ALLOWED_IMAGE_TYPES, self::ALLOWED_DOCUMENT_TYPES),
            'check_mime' => true,
            'check_extension' => true,
            'scan_virus' => false
        ], $config);
    }
    
    /**
     * Validate uploaded file
     */
    public function validate(): bool
    {
        $this->errors = [];
        
        // Check if file was uploaded
        if (!isset($this->file['error'])) {
            $this->errors[] = 'Arquivo não foi enviado corretamente.';
            return false;
        }
        
        // Check upload errors
        if ($this->file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($this->file['error']);
            return false;
        }
        
        // Check if file exists
        if (!isset($this->file['tmp_name']) || !is_uploaded_file($this->file['tmp_name'])) {
            $this->errors[] = 'Arquivo temporário não encontrado.';
            return false;
        }
        
        // Validate file size
        if (!$this->validateSize()) {
            return false;
        }
        
        // Validate MIME type
        if ($this->config['check_mime'] && !$this->validateMimeType()) {
            return false;
        }
        
        // Validate file extension
        if ($this->config['check_extension'] && !$this->validateExtension()) {
            return false;
        }
        
        // Additional security checks for images
        if ($this->isImage()) {
            if (!$this->validateImageIntegrity()) {
                return false;
            }
        }
        
        // Scan for malicious content
        if ($this->config['scan_virus'] && !$this->scanForMaliciousContent()) {
            return false;
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
        return $this->errors[0] ?? null;
    }
    
    /**
     * Validate file size
     */
    private function validateSize(): bool
    {
        if ($this->file['size'] > $this->config['max_size']) {
            $maxSizeMB = $this->config['max_size'] / 1048576;
            $this->errors[] = "O arquivo excede o tamanho máximo permitido de {$maxSizeMB}MB.";
            return false;
        }
        
        if ($this->file['size'] === 0) {
            $this->errors[] = 'O arquivo está vazio.';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate MIME type
     */
    private function validateMimeType(): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->config['allowed_types'])) {
            $this->errors[] = "Tipo de arquivo não permitido: $mimeType";
            return false;
        }
        
        // Double-check with uploaded MIME type
        if ($mimeType !== $this->file['type']) {
            $this->errors[] = 'Tipo de arquivo inconsistente.';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate file extension
     */
    private function validateExtension(): bool
    {
        $extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = $this->getAllowedExtensions();
        
        if (!in_array($extension, $allowedExtensions)) {
            $this->errors[] = "Extensão de arquivo não permitida: .$extension";
            return false;
        }
        
        return true;
    }
    
    /**
     * Get allowed extensions based on MIME types
     */
    private function getAllowedExtensions(): array
    {
        $extensions = [];
        
        foreach ($this->config['allowed_types'] as $mimeType) {
            switch ($mimeType) {
                case 'image/jpeg':
                    $extensions[] = 'jpg';
                    $extensions[] = 'jpeg';
                    break;
                case 'image/png':
                    $extensions[] = 'png';
                    break;
                case 'image/gif':
                    $extensions[] = 'gif';
                    break;
                case 'image/webp':
                    $extensions[] = 'webp';
                    break;
                case 'application/pdf':
                    $extensions[] = 'pdf';
                    break;
                case 'application/msword':
                    $extensions[] = 'doc';
                    break;
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    $extensions[] = 'docx';
                    break;
                case 'application/vnd.ms-excel':
                    $extensions[] = 'xls';
                    break;
                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    $extensions[] = 'xlsx';
                    break;
            }
        }
        
        return array_unique($extensions);
    }
    
    /**
     * Check if file is an image
     */
    private function isImage(): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);
        
        return in_array($mimeType, self::ALLOWED_IMAGE_TYPES);
    }
    
    /**
     * Validate image integrity
     */
    private function validateImageIntegrity(): bool
    {
        $imageInfo = @getimagesize($this->file['tmp_name']);
        
        if ($imageInfo === false) {
            $this->errors[] = 'O arquivo de imagem está corrompido ou não é uma imagem válida.';
            return false;
        }
        
        // Check for reasonable image dimensions
        if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
            $this->errors[] = 'As dimensões da imagem são muito grandes.';
            return false;
        }
        
        if ($imageInfo[0] < 10 || $imageInfo[1] < 10) {
            $this->errors[] = 'As dimensões da imagem são muito pequenas.';
            return false;
        }
        
        return true;
    }
    
    /**
     * Scan file for malicious content
     */
    private function scanForMaliciousContent(): bool
    {
        $content = file_get_contents($this->file['tmp_name']);
        
        // Check for PHP code in uploaded files
        $dangerousPatterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
            '/`.*`/i'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->errors[] = 'O arquivo contém conteúdo potencialmente malicioso.';
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'O arquivo excede o tamanho máximo permitido pelo servidor.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'O arquivo excede o tamanho máximo permitido pelo formulário.';
            case UPLOAD_ERR_PARTIAL:
                return 'O arquivo foi enviado parcialmente.';
            case UPLOAD_ERR_NO_FILE:
                return 'Nenhum arquivo foi enviado.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Pasta temporária não encontrada.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Falha ao gravar arquivo no disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload bloqueado por extensão.';
            default:
                return 'Erro desconhecido no upload.';
        }
    }
    
    /**
     * Move uploaded file to destination with secure naming
     */
    public function moveToSecureLocation(string $uploadDir): ?string
    {
        if (!$this->validate()) {
            return null;
        }
        
        // Generate secure filename
        $extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        $filename = $this->generateSecureFilename($extension);
        $destination = rtrim($uploadDir, '/') . '/' . $filename;
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $this->errors[] = 'Não foi possível criar o diretório de upload.';
                return null;
            }
        }
        
        // Move file
        if (!move_uploaded_file($this->file['tmp_name'], $destination)) {
            $this->errors[] = 'Não foi possível mover o arquivo para o destino.';
            return null;
        }
        
        // Set proper permissions
        chmod($destination, 0644);
        
        return $filename;
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename(string $extension): string
    {
        $uniqueId = bin2hex(random_bytes(16));
        $timestamp = time();
        
        return "{$timestamp}_{$uniqueId}.{$extension}";
    }
}