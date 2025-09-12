<?php
declare(strict_types=1);

namespace Src\Cache;

/**
 * Cache manager interface
 */
interface CacheInterface
{
    public function get(string $key, $default = null);
    public function set(string $key, $value, int $ttl = 3600): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function has(string $key): bool;
}

/**
 * File-based cache implementation
 */
class FileCache implements CacheInterface
{
    private string $cacheDir;
    private int $defaultTtl;
    
    public function __construct(string $cacheDir = null, int $defaultTtl = 3600)
    {
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir() . '/vetel_cache';
        $this->defaultTtl = $defaultTtl;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get(string $key, $default = null)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = file_get_contents($file);
        if ($data === false) {
            return $default;
        }
        
        $cached = unserialize($data);
        
        // Check expiration
        if ($cached['expires'] < time()) {
            unlink($file);
            return $default;
        }
        
        return $cached['value'];
    }
    
    public function set(string $key, $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $file = $this->getFilePath($key);
        
        $data = serialize([
            'value' => $value,
            'expires' => time() + $ttl
        ]);
        
        return file_put_contents($file, $data, LOCK_EX) !== false;
    }
    
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
}

/**
 * Memory cache implementation (for development/testing)
 */
class MemoryCache implements CacheInterface
{
    private array $cache = [];
    private int $defaultTtl;
    
    public function __construct(int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
    }
    
    public function get(string $key, $default = null)
    {
        if (!isset($this->cache[$key])) {
            return $default;
        }
        
        $cached = $this->cache[$key];
        
        if ($cached['expires'] < time()) {
            unset($this->cache[$key]);
            return $default;
        }
        
        return $cached['value'];
    }
    
    public function set(string $key, $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        
        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        return true;
    }
    
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }
    
    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }
    
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}

/**
 * Cache manager with tag support
 */
class CacheManager
{
    private CacheInterface $cache;
    private array $tags = [];
    
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
    
    /**
     * Get cached value
     */
    public function get(string $key, $default = null)
    {
        return $this->cache->get($key, $default);
    }
    
    /**
     * Set cached value
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }
    
    /**
     * Delete cached value
     */
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }
    
    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        return $this->cache->clear();
    }
    
    /**
     * Check if key exists in cache
     */
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }
    
    /**
     * Remember value (get from cache or execute callback)
     */
    public function remember(string $key, callable $callback, int $ttl = 3600)
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }
    
    /**
     * Tag cached values for group invalidation
     */
    public function tag(array $tags): self
    {
        $instance = clone $this;
        $instance->tags = $tags;
        return $instance;
    }
    
    /**
     * Invalidate cache by tags
     */
    public function invalidateTags(array $tags): bool
    {
        // Simple implementation - would need more sophisticated tag tracking
        // in production with Redis or Memcached
        foreach ($tags as $tag) {
            $this->delete("tag:$tag");
        }
        
        return true;
    }
    
    /**
     * Generate cache key for models
     */
    public function modelKey(string $model, int $id): string
    {
        return strtolower($model) . ':' . $id;
    }
    
    /**
     * Generate cache key for queries
     */
    public function queryKey(string $query, array $params = []): string
    {
        return 'query:' . md5($query . serialize($params));
    }
    
    /**
     * Cache database results
     */
    public function cacheQuery(string $query, array $params, callable $callback, int $ttl = 3600)
    {
        $key = $this->queryKey($query, $params);
        return $this->remember($key, $callback, $ttl);
    }
}