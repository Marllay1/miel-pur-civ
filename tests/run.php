<?php
declare(strict_types=1);

/**
 * Lance la suite de tests.
 *   php tests/run.php                 SQLite en mémoire (aucune configuration)
 *   php tests/run.php --mysql         base MariaDB/MySQL de test (TEST_DB_* : hôte, base miel_test, utilisateur, mot de passe)
 *   php tests/run.php --filter=09     ne lance que les scénarios dont le nom contient « 09 »
 * Code de sortie 0 si tout passe, 1 sinon.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/TestKit.php';

$opts = getopt('', ['mysql', 'filter:']);
T::$driver = isset($opts['mysql']) ? 'mysql' : 'sqlite';
if (T::$driver === 'mysql' && App\Core\Env::get('TEST_DB_PASSWORD') === null) {
    App\Core\Env::set('TEST_DB_PASSWORD', (string) App\Core\Env::get('DB_PASSWORD', ''));
}
$scenarios = require __DIR__ . '/scenarios.php';
$filter = $opts['filter'] ?? null;
$passed = 0;
$failed = [];
$start = microtime(true);
echo 'Suite de tests — moteur : ' . T::$driver . PHP_EOL . str_repeat('─', 72) . PHP_EOL;
foreach ($scenarios as $name => $test) {
    if ($filter !== null && !str_contains($name, $filter)) {
        continue;
    }
    $t0 = microtime(true);
    $before = T::$assertions;
    try {
        T::fresh();
        $test();
        $passed++;
        printf("  OK    %-58s %3d assertions %5.0f ms\n", $name, T::$assertions - $before, (microtime(true) - $t0) * 1000);
    } catch (\Throwable $e) {
        $failed[$name] = $e;
        printf("  ÉCHEC %-58s\n        → %s\n", $name, $e instanceof AssertionFailed ? $e->getMessage() : get_class($e) . ' : ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    }
}
echo str_repeat('─', 72) . PHP_EOL;
printf("%d scénario(s) réussi(s), %d échec(s), %d assertions, %.1f s\n", $passed, count($failed), T::$assertions, microtime(true) - $start);
exit($failed ? 1 : 0);
