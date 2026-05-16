<?php

/**
 * Simple in-memory cache for request lifecycle
 */
class Cache
{
    private static array $cache = [];
    private static int $defaultTtl = 60; // seconds
    
    public static function get(string $key)
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }
        
        $item = self::$cache[$key];
        
        // Check if expired
        if (time() > $item['expires']) {
            unset(self::$cache[$key]);
            return null;
        }
        
        return $item['value'];
    }
    
    public static function set(string $key, $value, int $ttl = null): void
    {
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + ($ttl ?? self::$defaultTtl)
        ];
    }
    
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }
    
    public static function forget(string $key): void
    {
        unset(self::$cache[$key]);
    }
    
    public static function flush(): void
    {
        self::$cache = [];
    }
    
    /**
     * Get or remember pattern
     */
    public static function remember(string $key, callable $callback, int $ttl = null)
    {
        if (self::has($key)) {
            return self::get($key);
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }
}

