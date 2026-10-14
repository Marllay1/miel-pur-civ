<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lecture des variables d'environnement.
 * Priorité : valeurs forcées (tests) > variables réelles du processus > fichier .env.
 */
final class Env
{
    private static array $file = [];
    private static array $overrides = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            self::$file[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$overrides)) {
            return self::$overrides[$key];
        }
        $real = getenv($key);
        if ($real !== false) {
            return $real;
        }
        return self::$file[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** Utilisé par les tests et les scripts CLI. */
    public static function set(string $key, ?string $value): void
    {
        if ($value === null) {
            unset(self::$overrides[$key]);
        } else {
            self::$overrides[$key] = $value;
        }
    }
}
