<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cache with pluggable drivers: file (default), apcu, redis (optional).
 * Redis is only used when explicitly enabled AND the extension is present —
 * never a hard requirement.
 */
final class Cache
{
    private static ?string $driver = null;
    private static ?\Redis $redis = null;

    private static function driver(): string
    {
        if (self::$driver !== null) {
            return self::$driver;
        }
        $configured = (string) Config::get('cache.driver', 'file');
        if ($configured === 'redis' && extension_loaded('redis')) {
            try {
                $redis = new \Redis();
                $ok = @$redis->connect(
                    (string) Config::get('cache.redis.host', '127.0.0.1'),
                    (int) Config::get('cache.redis.port', 6379),
                    1.5
                );
                $password = (string) Config::get('cache.redis.password', '');
                if ($ok && $password !== '') {
                    $redis->auth($password);
                }
                if ($ok) {
                    self::$redis = $redis;
                    return self::$driver = 'redis';
                }
            } catch (\Throwable) {
                // fall through to file
            }
        }
        if ($configured === 'apcu' && function_exists('apcu_fetch')) {
            return self::$driver = 'apcu';
        }
        return self::$driver = 'file';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        switch (self::driver()) {
            case 'redis':
                $raw = self::$redis->get(self::k($key));
                if ($raw === false) {
                    return $default;
                }
                $value = @unserialize($raw, ['allowed_classes' => false]);
                return $value === false && $raw !== serialize(false) ? $default : $value;
            case 'apcu':
                $success = false;
                $value = apcu_fetch(self::k($key), $success);
                return $success ? $value : $default;
            default:
                return self::fileGet($key, $default);
        }
    }

    public static function put(string $key, mixed $value, int $ttl = 3600): void
    {
        switch (self::driver()) {
            case 'redis':
                self::$redis->setex(self::k($key), max(1, $ttl), serialize($value));
                return;
            case 'apcu':
                apcu_store(self::k($key), $value, max(1, $ttl));
                return;
            default:
                self::filePut($key, $value, $ttl);
        }
    }

    public static function forget(string $key): void
    {
        switch (self::driver()) {
            case 'redis':
                self::$redis->del(self::k($key));
                return;
            case 'apcu':
                apcu_delete(self::k($key));
                return;
            default:
                $file = self::filePath($key);
                if (is_file($file)) {
                    @unlink($file);
                }
        }
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::get($key, '__MISS__');
        if ($value !== '__MISS__') {
            return $value;
        }
        $value = $callback();
        self::put($key, $value, $ttl);
        return $value;
    }

    /**
     * Atomic-ish counter increment with TTL (used by RateLimiter).
     */
    public static function increment(string $key, int $ttl = 60): int
    {
        if (self::driver() === 'redis') {
            $full = self::k($key);
            $value = (int) self::$redis->incr($full);
            if ($value === 1) {
                self::$redis->expire($full, $ttl);
            }
            return $value;
        }
        if (self::driver() === 'apcu') {
            $value = apcu_inc(self::k($key), 1, $success, $ttl);
            if ($value === false) {
                apcu_store(self::k($key), 1, $ttl);
                return 1;
            }
            return (int) $value;
        }

        // File driver: lock the counter file
        $file = self::filePath($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return 1;
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp) ?: '';
        $data = @unserialize($raw, ['allowed_classes' => false]);
        $now = time();
        if (!is_array($data) || ($data['expires'] ?? 0) < $now) {
            $data = ['expires' => $now + $ttl, 'value' => 0];
        }
        $data['value'] = (int) $data['value'] + 1;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, serialize($data));
        flock($fp, LOCK_UN);
        fclose($fp);
        return (int) $data['value'];
    }

    public static function flush(): void
    {
        if (self::driver() === 'redis') {
            $it = null;
            while (($keys = self::$redis->scan($it, self::k('*'), 500)) !== false) {
                if ($keys) {
                    self::$redis->del($keys);
                }
                if ($it === 0) {
                    break;
                }
            }
            return;
        }
        if (self::driver() === 'apcu') {
            apcu_clear_cache();
            return;
        }
        $dir = STORAGE_PATH . '/cache';
        if (is_dir($dir)) {
            foreach (glob($dir . '/*/*.cache') ?: [] as $file) {
                @unlink($file);
            }
            foreach (glob($dir . '/*.cache') ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    // -- File driver internals -----------------------------------------------

    private static function fileGet(string $key, mixed $default): mixed
    {
        $file = self::filePath($key);
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return $default;
        }
        $data = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($data) || !array_key_exists('value', $data)) {
            return $default;
        }
        if (($data['expires'] ?? 0) !== 0 && $data['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        return $data['value'];
    }

    private static function filePut(string $key, mixed $value, int $ttl): void
    {
        $file = self::filePath($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $payload = serialize(['expires' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value]);
        @file_put_contents($file . '.tmp', $payload, LOCK_EX);
        @rename($file . '.tmp', $file);
    }

    private static function filePath(string $key): string
    {
        $hash = sha1($key);
        return STORAGE_PATH . '/cache/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    private static function k(string $key): string
    {
        return 'kwc:' . $key;
    }
}
