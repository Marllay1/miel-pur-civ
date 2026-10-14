<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session : native en HTTP, tableau en mémoire en CLI (tests, scripts).
 */
final class Session
{
    private static array $fake = [];
    private static bool $native = false;
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        self::$started = true;
        if (PHP_SAPI === 'cli') {
            self::$native = false;
            return;
        }
        self::$native = true;
        $dir = BASE_PATH . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '86400');
        session_name('mpci_sess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Cookie::secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Remplace la session (tests) et renvoie l'ancienne. */
    public static function fake(array $data = []): array
    {
        $old = self::$fake;
        self::$fake = $data;
        self::$native = false;
        self::$started = true;
        return $old;
    }

    public static function &store(): array
    {
        if (self::$native) {
            return $_SESSION;
        }
        return self::$fake;
    }

    public static function all(): array
    {
        return self::store();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::store()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $s = &self::store();
        $s[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset(self::store()[$key]);
    }

    public static function forget(string $key): void
    {
        $s = &self::store();
        unset($s[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $v = self::get($key, $default);
        self::forget($key);
        return $v;
    }

    public static function flash(string $type, string $message): void
    {
        $s = &self::store();
        $s['_flash'][$type][] = $message;
    }

    /** @return array<string, string[]> */
    public static function pullFlashes(): array
    {
        return (array) self::pull('_flash', []);
    }

    public static function regenerate(): void
    {
        if (self::$native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $s = &self::store();
        $s = [];
        if (self::$native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
