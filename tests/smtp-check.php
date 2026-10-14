<?php
declare(strict_types=1);

/**
 * Vérification de l'envoi d'e-mails par SMTP.
 *
 * 1) Contre un serveur de test Mailpit (local) — contrôle complet, messages relus par l'API de Mailpit :
 *      php tests/smtp-check.php --mailpit
 *    Prérequis : mailpit --smtp 127.0.0.1:1025 --listen 127.0.0.1:8025 --smtp-auth-accept-any --smtp-auth-allow-insecure
 *                mailpit --smtp 127.0.0.1:1587 --listen 127.0.0.1:8026 --smtp-tls-cert cert.pem --smtp-tls-key key.pem
 *                        --smtp-require-starttls --smtp-auth-file auth.txt     (+ MAILPIT_TLS_PASSWORD dans l'environnement)
 *
 * 2) Envoi réel avec la configuration du fichier .env (MAIL_DRIVER=smtp, SMTP_*) :
 *      php tests/smtp-check.php --to=vous@exemple.ci
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Env;
use App\Core\Mailer;
use App\Core\Smtp;

$opts = getopt('', ['mailpit', 'to:']);
$passed = 0;
$failed = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK    ' : '  ÉCHEC ') . $label . PHP_EOL;
}
function api(string $base, string $path): ?array
{
    $raw = @file_get_contents($base . $path);
    return $raw === false ? null : json_decode($raw, true);
}

if (isset($opts['to'])) {
    $to = (string) $opts['to'];
    echo 'Envoi réel via ' . Env::get('MAIL_DRIVER', 'log') . ' (' . Env::get('SMTP_HOST', '—') . ':' . Env::get('SMTP_PORT', '—') . ") vers $to\n";
    $ok = Mailer::send($to, 'Test d\'envoi — Miel Pur de Côte d\'Ivoire', "Bonjour,\n\nCeci est un message de test envoyé le " . gmdate('d/m/Y H:i') . " UTC.\n\n" . base_url());
    echo $ok ? "✔ Message accepté par le serveur. Vérifiez la boîte de réception (et les indésirables).\n" : "✘ Échec : consultez storage/logs/app-" . gmdate('Y-m-d') . ".log\n";
    exit($ok ? 0 : 1);
}

if (!isset($opts['mailpit'])) {
    fwrite(STDERR, "Usage : --mailpit ou --to=adresse\n");
    exit(2);
}

Env::set('APP_ENV', 'production');
Env::set('MAIL_DRIVER', 'smtp');
Env::set('MAIL_FROM_ADDRESS', 'boutique@miel-pur.test');
Env::set('MAIL_FROM_NAME', 'Miel Pur de Côte d\'Ivoire');
@file_get_contents('http://127.0.0.1:8025/api/v1/messages', false, stream_context_create(['http' => ['method' => 'DELETE']]));
@file_get_contents('http://127.0.0.1:8026/api/v1/messages', false, stream_context_create(['http' => ['method' => 'DELETE']]));

echo "Test SMTP contre Mailpit\n" . str_repeat('─', 60) . "\n";

// 1. SMTP sans chiffrement (serveur local)
Env::set('SMTP_HOST', '127.0.0.1');
Env::set('SMTP_PORT', '1025');
Env::set('SMTP_ENCRYPTION', 'none');
Env::set('SMTP_USERNAME', '');
$text = "Bonjour Aya,\n\nNous avons bien reçu votre commande MPCI-20261014-ABCDEF12.\n.ligne commençant par un point\nSuivi : https://boutique.example.ci/commande/MPCI-20261014-ABCDEF12\n\n— Miel Pur";
check(Mailer::send('client@example.test', "Commande reçue — accents éàù\r\nBcc: pirate@example.test", $text), 'Envoi SMTP accepté (port 1025)');
$list = api('http://127.0.0.1:8025', '/api/v1/messages');
$msg = $list['messages'][0] ?? null;
check($msg !== null && ($msg['To'][0]['Address'] ?? '') === 'client@example.test', 'Message reçu par le serveur');
check($msg !== null && str_contains($msg['Subject'], 'accents éàù'), 'Objet UTF-8 correctement encodé');
check($msg !== null && count($msg['Bcc'] ?? []) === 0 && count($msg['Cc'] ?? []) === 0 && count($msg['To'] ?? []) === 1
    && !in_array('pirate@example.test', array_column(array_merge($msg['To'] ?? [], $msg['Cc'] ?? [], $msg['Bcc'] ?? []), 'Address'), true), 'Injection d\'en-tête neutralisée (aucun destinataire ajouté)');
$raw = $msg ? (string) @file_get_contents('http://127.0.0.1:8025/api/v1/message/' . $msg['ID'] . '/raw') : '';
check($raw !== '' && !preg_match('/^Bcc:/mi', $raw), 'Aucun en-tête Bcc dans le message brut');
check(($msg['From']['Name'] ?? '') === 'Miel Pur de Côte d\'Ivoire', 'Nom d\'expéditeur');
$full = $msg ? api('http://127.0.0.1:8025', '/api/v1/message/' . $msg['ID']) : null;
check($full !== null && str_contains($full['Text'], 'MPCI-20261014-ABCDEF12') && str_contains($full['Text'], "\n.ligne commençant"), 'Corps texte intact (transparence SMTP des points)');
check($full !== null && str_contains($full['HTML'], '<a href="https://boutique.example.ci/commande/MPCI-20261014-ABCDEF12"'), 'Version HTML avec lien cliquable');
check($full !== null && !str_contains($full['HTML'], '<script'), 'HTML sans script');

// 2. STARTTLS + authentification
$password = (string) (getenv('MAILPIT_TLS_PASSWORD') ?: @file_get_contents('C:/Users/Lenovo/tools/mailpit/test/smtp-test-password.txt'));
Env::set('SMTP_PORT', '1587');
Env::set('SMTP_ENCRYPTION', 'tls');
Env::set('SMTP_USERNAME', 'boutique');
Env::set('SMTP_PASSWORD', $password);
Env::set('SMTP_HOST', 'localhost');
Env::set('SMTP_ALLOW_SELF_SIGNED', '0');
check(!Mailer::send('client@example.test', 'Certificat non vérifié', 'x'), 'Certificat auto-signé refusé par défaut (vérification TLS active)');
Env::set('SMTP_ALLOW_SELF_SIGNED', '1');
check(Mailer::send('client@example.test', 'STARTTLS et authentification', "Bonjour,\nMessage chiffré."), 'Envoi STARTTLS + AUTH accepté (port 1587)');
$tls = api('http://127.0.0.1:8026', '/api/v1/messages');
check(($tls['messages'][0]['Subject'] ?? '') === 'STARTTLS et authentification', 'Message reçu via la connexion chiffrée');
Env::set('SMTP_PASSWORD', 'mauvais-mot-de-passe');
check(!Mailer::send('client@example.test', 'Mauvais mot de passe', 'x'), 'Authentification refusée avec un mauvais mot de passe');
Env::set('SMTP_PASSWORD', $password);
Env::set('SMTP_ENCRYPTION', 'none');
check(!Mailer::send('client@example.test', 'Sans STARTTLS', 'x'), 'Serveur exigeant STARTTLS : envoi en clair refusé');

// 3. Journal : aucun mot de passe en clair
$smtp = new Smtp('localhost', 1587, 'tls', 'boutique', $password, 10, false);
try {
    $smtp->send('boutique@miel-pur.test', 'Miel Pur', 'client@example.test', 'Journal', 'x', '<p>x</p>');
} catch (\Throwable) {
}
check(!str_contains(implode("\n", $smtp->log), $password) && !str_contains(implode("\n", $smtp->log), base64_encode("\0boutique\0" . $password)), 'Identifiants masqués dans le journal SMTP');

echo str_repeat('─', 60) . "\n$passed contrôle(s) réussi(s), $failed échec(s)\n";
exit($failed ? 1 : 0);
