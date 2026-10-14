<?php
declare(strict_types=1);

/**
 * Restaure une sauvegarde produite par bin/backup.php (remplace TOUTES les données actuelles).
 *   php bin/restore.php storage/backups/backup-AAAAMMJJ-HHMMSS.sql.gz --force
 * Pré-requis : schéma installé (php bin/install.php). Les images se restaurent en dézippant uploads-*.zip dans public/uploads.
 */

require __DIR__ . '/_cli.php';

use App\Core\DB;
use App\Core\Migrator;

$opts = cli_options($argv);
$path = null;
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        $path = $arg;
    }
}
if ($path === null || !is_file($path)) {
    fail('Indiquez le fichier de sauvegarde à restaurer.');
}
$content = str_ends_with($path, '.gz') ? gzdecode((string) file_get_contents($path)) : file_get_contents($path);
if (!is_string($content) || !str_contains($content, '-- MIEL-PUR-BACKUP v1')) {
    fail('Fichier non reconnu (sauvegarde bin/backup.php attendue).');
}
if (empty($opts['force'])) {
    fail('Cette opération REMPLACE toutes les données. Relancez avec --force pour confirmer.');
}

Migrator::migrate();
$pdo = DB::pdo();
$driver = DB::driver();
$driver === 'mysql' ? $pdo->exec('SET FOREIGN_KEY_CHECKS = 0') : $pdo->exec('PRAGMA foreign_keys = OFF');
$count = 0;
$pdo->beginTransaction();
try {
    foreach (preg_split("/;\n(?=(?:DELETE FROM|INSERT INTO) `)/", $content) as $statement) {
        $statement = trim(preg_replace('/^--.*$/m', '', $statement));
        $statement = rtrim($statement, ";\n ");
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
        $count++;
    }
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    fail('Restauration annulée : ' . $e->getMessage());
} finally {
    $driver === 'mysql' ? $pdo->exec('SET FOREIGN_KEY_CHECKS = 1') : $pdo->exec('PRAGMA foreign_keys = ON');
}
out("✔ Restauration terminée ($count instructions).");
