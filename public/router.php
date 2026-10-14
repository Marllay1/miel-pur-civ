<?php
declare(strict_types=1);

/**
 * Routeur pour le serveur de développement intégré :
 *   php -S localhost:8000 -t public public/router.php
 * Sert les fichiers statiques existants (sauf .php et fichiers cachés), sinon passe la main à index.php.
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
    http_response_code(400);
    header_remove('X-Powered-By');
    exit('Requête invalide.');
}
$file = realpath(__DIR__ . $path);
if (
    $path !== '/'
    && $file !== false
    && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)
    && is_file($file)
    && !preg_match('/\.(php|phtml|phar|htaccess)$/i', $file)
    && !preg_match('#/\.#', str_replace('\\', '/', $path))
) {
    return false;
}
require __DIR__ . '/index.php';
