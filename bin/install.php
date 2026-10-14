<?php
declare(strict_types=1);

/**
 * Installation : crée .env (secrets générés), les dossiers, la base, applique les migrations et les données de référence.
 *   php bin/install.php            installation (rôles, pages, FAQ, zones de livraison)
 *   php bin/install.php --catalog  ajoute le catalogue initial (gamme confirmée ; --demo reste accepté)
 *   php bin/install.php --sqlite   (nouvelle installation) configure SQLite au lieu de MySQL
 * Le script est rejouable : il n'écrase ni le .env existant ni les données.
 */

require __DIR__ . '/_cli.php';

use App\Core\DB;
use App\Core\Env;
use App\Core\Migrator;

$opts = cli_options($argv);
$envFile = BASE_PATH . '/.env';

if (!is_file($envFile)) {
    $example = (string) file_get_contents(BASE_PATH . '/.env.example');
    $example = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . bin2hex(random_bytes(32)), $example);
    $example = preg_replace('/^DEMO_PAYMENT_SECRET=.*$/m', 'DEMO_PAYMENT_SECRET=' . bin2hex(random_bytes(32)), $example);
    if (!empty($opts['sqlite'])) {
        $example = preg_replace('/^DB_DRIVER=.*$/m', 'DB_DRIVER=sqlite', $example);
        $example = preg_replace('/^DB_DATABASE=.*$/m', 'DB_DATABASE=database.sqlite', $example);
    }
    if (file_put_contents($envFile, $example) === false) {
        fail('Impossible d\'écrire .env');
    }
    @chmod($envFile, 0600);
    out('✔ Fichier .env créé avec APP_KEY et DEMO_PAYMENT_SECRET générés.');
    out('  → Vérifiez les paramètres de base de données dans .env, puis relancez ce script si la connexion échoue.');
    Env::load($envFile);
} else {
    out('• Fichier .env existant conservé.');
}

foreach (['storage/logs', 'storage/mail', 'storage/sessions', 'storage/backups', 'storage/cache', 'public/uploads/products'] as $dir) {
    $path = BASE_PATH . '/' . $dir;
    if (!is_dir($path) && !@mkdir($path, 0755, true)) {
        fail("Impossible de créer $dir");
    }
}
out('✔ Dossiers storage/ et public/uploads/ prêts.');

if (strlen((string) Env::get('APP_KEY', '')) < 32) {
    fail('APP_KEY absente ou trop courte dans .env (64 caractères hexadécimaux attendus).');
}

$driver = Env::get('DB_DRIVER', 'mysql');
if ($driver === 'mysql') {
    $name = (string) Env::get('DB_DATABASE', 'miel_pur');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        fail('Nom de base invalide : ' . $name);
    }
    try {
        DB::pdo();
    } catch (\PDOException $e) {
        // Tentative de création de la base si l'utilisateur en a le droit.
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306')),
                Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            out("✔ Base « $name » créée.");
            DB::setPdo(null);
            DB::pdo();
        } catch (\PDOException $e2) {
            fail('Connexion MySQL impossible : ' . $e2->getMessage() . "\nCréez la base « $name » (phpMyAdmin ou ligne de commande) et vérifiez DB_USERNAME / DB_PASSWORD dans .env.");
        }
    }
}

$applied = Migrator::migrate();
out($applied ? '✔ Migrations appliquées : ' . implode(', ', $applied) : '• Schéma déjà à jour.');

(require BASE_PATH . '/database/seeds/base.php')();
out('✔ Données de référence : rôles, permissions, pages FR/EN, FAQ FR/EN, zones de livraison.');

if (!empty($opts['catalog']) || !empty($opts['demo'])) {
    (require BASE_PATH . '/database/seeds/catalog.php')();
    out('✔ Catalogue initial chargé (5 produits, 10 formats, FR/EN) ; ajustez les stocks dans l\'administration.');
}

$admins = (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'admin'");
out('');
out('Installation terminée (' . $driver . ').');
if ($admins === 0) {
    out('Étape suivante : créer le premier administrateur :');
    out('  php bin/create-admin.php --email=vous@exemple.ci --first=Prénom --last=Nom');
}
out('Serveur de développement : php -S localhost:8000 -t public public/router.php');
