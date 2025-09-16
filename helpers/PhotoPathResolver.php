<?php

declare(strict_types=1);

namespace Helpers;

use Config\Config;

/**
 * PhotoPathResolver - Separates filesystem paths from web URL paths
 *
 * Solves the path duplication issue where filesystem paths were used as URLs,
 * causing Apache to create doubled paths like /var/www/html/var/www/html/img/album/
 */
class PhotoPathResolver
{
    /**
     * Get the absolute filesystem path for photo storage
     * Used for file operations like upload, delete, PDF generation
     */
    public static function getStorageDir(): string
    {
        // Try new specific variable first, fallback to legacy
        $storageDir = Config::get('PHOTO_STORAGE_DIR', null);

        if ($storageDir !== null) {
            return $storageDir;
        }

        // Legacy fallback - determine based on environment
        if (file_exists('/.dockerenv')) {
            // Docker environment - use volume path
            return '/var/www/html/' . Config::get('PHOTO_STORAGE_PATH', 'img/album');
        } else {
            // Local development - use relative path
            $legacyPath = Config::get('PHOTO_STORAGE_PATH', 'img/album');
            if (str_starts_with($legacyPath, '/')) {
                // Already absolute
                return $legacyPath;
            } else {
                // Make relative to project root
                return __DIR__ . '/../' . $legacyPath;
            }
        }
    }

    /**
     * Get the web-relative path for URL generation
     * Used for HTML img src attributes and JavaScript URLs
     */
    public static function getWebPath(): string
    {
        // Try new specific variable first
        $webPath = Config::get('PHOTO_WEB_PATH', null);

        if ($webPath !== null) {
            return $webPath;
        }

        // Legacy fallback - always use relative web path
        $legacyPath = Config::get('PHOTO_STORAGE_PATH', 'img/album');

        // Extract relative part from absolute path if needed
        if (str_starts_with($legacyPath, '/var/www/html/')) {
            return substr($legacyPath, strlen('/var/www/html/'));
        } elseif (str_starts_with($legacyPath, __DIR__ . '/../')) {
            return substr($legacyPath, strlen(__DIR__ . '/../'));
        } else {
            // Already relative or simple path
            return ltrim($legacyPath, '/');
        }
    }

    /**
     * Generate full web URL for a photo filename
     * Returns URL suitable for HTML img src attributes
     */
    public static function getFullWebUrl(string $filename): string
    {
        $webPath = self::getWebPath();
        return '/' . trim($webPath, '/') . '/' . $filename;
    }

    /**
     * Get absolute filesystem path for a specific photo
     * Used for file operations and existence checks
     */
    public static function getFullStoragePath(string $filename): string
    {
        $storageDir = self::getStorageDir();
        return rtrim($storageDir, '/') . '/' . $filename;
    }

    /**
     * Check if a photo file exists on the filesystem
     */
    public static function photoExists(string $filename): bool
    {
        return file_exists(self::getFullStoragePath($filename));
    }

    /**
     * Get debug information about current path configuration
     * Useful for troubleshooting path issues
     */
    public static function getDebugInfo(): array
    {
        return [
            'storage_dir' => self::getStorageDir(),
            'web_path' => self::getWebPath(),
            'is_docker' => file_exists('/.dockerenv'),
            'legacy_storage_path' => Config::get('PHOTO_STORAGE_PATH', 'img/album'),
            'config_storage_dir' => Config::get('PHOTO_STORAGE_DIR', null),
            'config_web_path' => Config::get('PHOTO_WEB_PATH', null),
        ];
    }
}