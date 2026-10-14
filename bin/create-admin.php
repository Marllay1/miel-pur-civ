<?php
declare(strict_types=1);

/**
 * Crée un compte du personnel (par défaut : administrateur).
 *   php bin/create-admin.php --email=admin@exemple.ci --first=Awa --last=Koné [--role=admin|catalog_manager|order_manager] [--password=...]
 * Sans --password, un mot de passe aléatoire est généré et affiché UNE seule fois.
 * Avec --reset-password, réinitialise le mot de passe d'un compte existant.
 */

require __DIR__ . '/_cli.php';

use App\Core\Audit;
use App\Core\DB;
use App\Services\AccountService;

$opts = cli_options($argv);
$email = mb_strtolower(trim((string) ($opts['email'] ?? '')));
if ($email === '') {
    $email = mb_strtolower(prompt('E-mail'));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('E-mail invalide.');
}
$role = (string) ($opts['role'] ?? 'admin');
if (!in_array($role, ['admin', 'catalog_manager', 'order_manager'], true)) {
    fail('Rôle invalide (admin, catalog_manager, order_manager).');
}
$password = is_string($opts['password'] ?? null) ? $opts['password'] : null;
$generated = false;
if ($password === null) {
    $password = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(18))), 0, 16) . random_int(10, 99);
    $generated = true;
}
if ($problem = AccountService::passwordProblem($password)) {
    fail($problem);
}

$existing = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
if ($existing !== null) {
    if (empty($opts['reset-password'])) {
        fail('Un compte existe déjà avec cet e-mail. Ajoutez --reset-password pour réinitialiser son mot de passe.');
    }
    DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'is_active' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $existing['id']]);
    Audit::log('user.password_reset_cli', 'user', (int) $existing['id'], [], null);
    out('✔ Mot de passe réinitialisé pour ' . $email . '.');
} else {
    $first = (string) ($opts['first'] ?? '') ?: prompt('Prénom', 'Admin');
    $last = (string) ($opts['last'] ?? '') ?: prompt('Nom', 'Miel Pur');
    $res = AccountService::register(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => $password, 'phone' => null], $role);
    if (!$res['ok']) {
        fail((string) $res['error']);
    }
    Audit::log('user.create_cli', 'user', (int) $res['user']['id'], ['role' => $role], null);
    out('✔ Compte « ' . $role . ' » créé : ' . $email);
}
if ($generated) {
    out('Mot de passe généré (affiché une seule fois, à changer après la première connexion) : ' . $password);
}
out('Connexion : ' . base_url() . '/admin/connexion');
