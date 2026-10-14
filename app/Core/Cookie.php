<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cookies mis en file puis envoyés avec la réponse (lisibles par les tests).
 */
final class Cookie
{
    /** @var array<string, array{value:string, expires:int, httponly:bool}> */
    public static array $queued = [];

    public static function queue(string $name, string $value, int $seconds, bool $httpOnly = true): void
    {
        self::$queued[$name] = ['value' => $value, 'expires' => $seconds > 0 ? time() + $seconds : 0, 'httponly' => $httpOnly];
    }

    public static function forget(string $name): void
    {
        self::$queued[$name] = ['value' => '', 'expires' => time() - 3600, 'httponly' => true];
    }

    public static function secure(): bool
    {
        return str_starts_with((string) Env::get('APP_URL', ''), 'https://');
    }

    public static function flush(): void
    {
        foreach (self::$queued as $name => $c) {
            setcookie($name, $c['value'], [
                'expires' => $c['expires'],
                'path' => '/',
                'secure' => self::secure(),
                'httponly' => $c['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        self::$queued = [];
    }
}
