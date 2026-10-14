<?php
declare(strict_types=1);

/** Amorçage commun des scripts en ligne de commande. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

/** @return array<string, string|bool> options --cle=valeur ou --drapeau */
function cli_options(array $argv): array
{
    $opts = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $arg, $m)) {
            $opts[$m[1]] = $m[2] ?? true;
        }
    }
    return $opts;
}

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, 'ERREUR : ' . $message . PHP_EOL);
    exit($code);
}

function prompt(string $label, string $default = ''): string
{
    fwrite(STDOUT, $label . ($default !== '' ? " [$default]" : '') . ' : ');
    $line = fgets(STDIN);
    $value = $line === false ? '' : trim($line);
    return $value === '' ? $default : $value;
}
