<?php

namespace Helpers;

/**
 * Helper class for file operations and name sanitization
 */
class FileHelper
{
    /**
     * Sanitize filename removing special characters, accents, spaces
     * and ensuring uniqueness with timestamp
     * 
     * @param string $originalName Original filename with extension
     * @param int $diarioObraId ID of the diary work
     * @param int $index Index of the file in batch upload
     * @return string Sanitized unique filename
     */
    public static function generateUniqueImageName(string $originalName, int $diarioObraId, int $index): string
    {
        // Extract extension
        $pathInfo = pathinfo($originalName);
        $extension = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : 'jpg';
        
        // Validate extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        if (!in_array($extension, $allowedExtensions)) {
            $extension = 'jpg'; // Default to jpg if invalid
        }
        
        // Generate unique identifier with microseconds for uniqueness
        $timestamp = date('YmdHis');
        $microseconds = substr(microtime(), 2, 6); // Get 6 digits of microseconds
        
        // Create standardized filename
        // Format: diario-{id}-{timestamp}{microseconds}-{index}.{ext}
        $fileName = sprintf(
            'diario-%d-%s%s-%03d.%s',
            $diarioObraId,
            $timestamp,
            $microseconds,
            $index,
            $extension
        );
        
        return $fileName;
    }
    
    /**
     * Sanitize any filename removing problematic characters
     * Useful for logos and other uploads
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Separate name and extension
        $pathInfo = pathinfo($filename);
        $name = isset($pathInfo['filename']) ? $pathInfo['filename'] : 'file';
        $extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
        
        // Remove accents and convert to ASCII
        $name = self::removeAccents($name);
        
        // Replace spaces and special characters with hyphens
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
        
        // Remove multiple consecutive hyphens
        $name = preg_replace('/-+/', '-', $name);
        
        // Remove leading and trailing hyphens
        $name = trim($name, '-');
        
        // Convert to lowercase
        $name = strtolower($name);
        
        // If name is empty after sanitization, use default
        if (empty($name)) {
            $name = 'file';
        }
        
        // Limit length to prevent filesystem issues
        if (strlen($name) > 100) {
            $name = substr($name, 0, 100);
        }
        
        // Add timestamp for uniqueness
        $uniqueSuffix = date('Ymd-His');
        $name = $name . '-' . $uniqueSuffix;
        
        // Reconstruct filename with extension
        if (!empty($extension)) {
            return $name . '.' . strtolower($extension);
        }
        
        return $name;
    }
    
    /**
     * Remove accents from string
     * 
     * @param string $str String with possible accents
     * @return string String without accents
     */
    private static function removeAccents(string $str): string
    {
        $unwanted = array(
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 
            'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 
            'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 
            'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 
            'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 
            'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        );
        
        return strtr($str, $unwanted);
    }
    
    /**
     * Check if file already exists and generate alternative name if needed
     * 
     * @param string $filePath Full path to check
     * @param string $fileName Current filename
     * @return string Available filename
     */
    public static function ensureUniqueFilename(string $filePath, string $fileName): string
    {
        // If file doesn't exist, return original name
        if (!file_exists($filePath)) {
            return $fileName;
        }
        
        // Extract parts
        $pathInfo = pathinfo($fileName);
        $name = isset($pathInfo['filename']) ? $pathInfo['filename'] : 'file';
        $extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
        
        // Try adding counter until we find available name
        $counter = 1;
        do {
            $newName = $name . '-' . $counter;
            if (!empty($extension)) {
                $newName .= '.' . $extension;
            }
            
            $newPath = dirname($filePath) . '/' . $newName;
            $counter++;
            
            // Safety limit to prevent infinite loop
            if ($counter > 1000) {
                // Use timestamp as last resort
                $newName = $name . '-' . time() . '-' . mt_rand(1000, 9999);
                if (!empty($extension)) {
                    $newName .= '.' . $extension;
                }
                break;
            }
        } while (file_exists($newPath));
        
        return $newName;
    }
    
    /**
     * Validate image file
     * 
     * @param array $file $_FILES array element
     * @param int $maxSize Maximum size in bytes (default 5MB)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateImageUpload(array $file, int $maxSize = 10485760): array
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo permitido pelo servidor',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido pelo formulário',
                UPLOAD_ERR_PARTIAL => 'Upload do arquivo foi parcial',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP'
            ];
            
            $error = isset($errorMessages[$file['error']]) 
                ? $errorMessages[$file['error']] 
                : 'Erro desconhecido no upload';
                
            return ['valid' => false, 'error' => $error];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $maxSizeMB = $maxSize / 1048576;
            return [
                'valid' => false, 
                'error' => "Arquivo excede o tamanho máximo de {$maxSizeMB}MB"
            ];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            return [
                'valid' => false,
                'error' => 'Tipo de arquivo não permitido. Use apenas imagens (JPG, PNG, GIF, WEBP, BMP)'
            ];
        }
        
        // Additional check for image validity
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'error' => 'Arquivo não é uma imagem válida'
            ];
        }
        
        return ['valid' => true, 'error' => null];
    }
}