<?php

namespace FlujosDimension\Core;

class CacheManager
{
    private string $cacheDir;
    
    public function __construct(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }
    
    public function get(string $key, $default = null)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] && $data['expires'] < time()) {
            unlink($file);
            return $default;
        }
        
        return $data['value'];
    }
    
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : null
        ];
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }
    
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        return file_exists($file) ? unlink($file) : true;
    }
    
    public function deletePattern(string $pattern): void
    {
        $files = glob($this->cacheDir . '/' . $pattern . '.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
}
