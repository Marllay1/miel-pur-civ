<?php
declare(strict_types=1);

/**
 * Amorçage commun (HTTP, CLI, tests) : constantes, autoloader, helpers, environnement.
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require APP_PATH . '/helpers.php';

App\Core\Env::load(BASE_PATH . '/.env');
date_default_timezone_set('UTC');

// Jamais d'erreur PHP affichée aux visiteurs hors mode débogage (indépendamment du php.ini).
if (!App\Core\Env::bool('APP_DEBUG')) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');
}
ini_set('expose_php', '0');
mb_internal_encoding('UTF-8');

// Textes de l'interface modifiés en administration (Textes du site), prioritaires sur app/lang/*.php.
App\Core\Lang::$overrideLoader = static fn (string $locale): array => App\Services\TextOverrides::load($locale);
