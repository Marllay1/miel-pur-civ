<?php
declare(strict_types=1);

/**
 * Sauvegarde des DONNÉES (toutes les tables) au format SQL compressé, plus les images téléversées (zip).
 *   php bin/backup.php [--no-uploads]
 * Fichiers produits dans storage/backups/ ; conserve les BACKUP_KEEP plus récents (14 par défaut).
 * Le schéma est recréé par bin/install.php ; la restauration se fait avec bin/restore.php.
 * Alternative MySQL : mysqldump ou export phpMyAdmin (voir docs/SAUVEGARDE.md).
 */

require __DIR__ . '/_cli.php';

use App\Core\DB;
use App\Core\Env;

$opts = cli_options($argv);
$dir = BASE_PATH . '/storage/backups';
if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
    fail('Dossier storage/backups indisponible.');
}

// Ordre respectant les clés étrangères (parents d'abord).
$tables = ['roles', 'permissions', 'role_permissions', 'users', 'password_resets', 'addresses', 'categories', 'products', 'product_variants',
    'product_images', 'stock_movements', 'carts', 'cart_items', 'shipping_zones', 'promo_codes', 'orders', 'order_items', 'order_status_history',
    'payments', 'payment_events', 'shipments', 'quote_requests', 'contact_messages', 'reviews', 'settings', 'pages', 'faqs',
    'newsletter_subscribers', 'audit_log', 'rate_limits'];

$pdo = DB::pdo();
$stamp = gmdate('Ymd-His');
$file = "$dir/backup-$stamp.sql.gz";
$gz = gzopen($file, 'wb6');
if ($gz === false) {
    fail('Écriture impossible : ' . $file);
}
gzwrite($gz, "-- Sauvegarde Miel Pur de Côte d'Ivoire — " . gmdate('Y-m-d H:i:s') . " UTC — moteur " . DB::driver() . "\n");
gzwrite($gz, "-- MIEL-PUR-BACKUP v1\n");
$rows = 0;
foreach ($tables as $table) {
    gzwrite($gz, "DELETE FROM `$table`;\n");
    $stmt = $pdo->query("SELECT * FROM `$table`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $values = array_map(static function ($v) use ($pdo) {
            if ($v === null) {
                return 'NULL';
            }
            if (is_int($v) || is_float($v)) {
                return (string) $v;
            }
            return $pdo->quote((string) $v);
        }, array_values($row));
        $cols = implode(', ', array_map(static fn ($c) => "`$c`", array_keys($row)));
        gzwrite($gz, "INSERT INTO `$table` ($cols) VALUES (" . implode(', ', $values) . ");\n");
        $rows++;
    }
}
gzclose($gz);
out(sprintf('✔ Données : %s (%d lignes, %s Ko)', basename($file), $rows, number_format(filesize($file) / 1024, 1, ',', ' ')));

if (empty($opts['no-uploads'])) {
    $uploads = BASE_PATH . '/public/uploads';
    if (class_exists(ZipArchive::class)) {
        $zipFile = "$dir/uploads-$stamp.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
            $n = 0;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && preg_match('/\.(webp|jpe?g|png)$/i', $f->getFilename())) {
                    $zip->addFile($f->getPathname(), substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', $uploads)) + 1));
                    $n++;
                }
            }
            $zip->close();
            out($n > 0 ? "✔ Images : " . basename($zipFile) . " ($n fichier(s))" : '• Aucune image téléversée.');
            if ($n === 0) {
                @unlink($zipFile);
            }
        }
    } else {
        out('• Extension zip absente : copiez manuellement le dossier public/uploads.');
    }
}

$keep = max(1, (int) Env::get('BACKUP_KEEP', '14'));
foreach (['backup-*.sql.gz', 'uploads-*.zip'] as $pattern) {
    $files = glob("$dir/$pattern") ?: [];
    rsort($files);
    foreach (array_slice($files, $keep) as $old) {
        @unlink($old);
    }
}
out("Rotation : $keep sauvegarde(s) conservée(s). Copiez régulièrement storage/backups hors du serveur.");
