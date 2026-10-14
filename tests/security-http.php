<?php
declare(strict_types=1);

/**
 * Tests de sécurité en boîte noire contre un serveur HTTP réel.
 *   php -S localhost:8000 -t public public/router.php   (dans un autre terminal)
 *   php tests/security-http.php [http://localhost:8000]
 * Crée un compte client jetable dans la base configurée (.env) ; à lancer sur un environnement de développement.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$passed = 0;
$failed = 0;

function http(string $method, string $url, array $headers = [], ?string $body = null, ?string $cookie = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PATH_AS_IS => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($cookie !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $hsize);
    $hdrs = [];
    $cookies = [];
    foreach (explode("\r\n", $head) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $k = strtolower(trim($k));
            if ($k === 'set-cookie') {
                $cookies[] = trim($v);
            }
            $hdrs[$k] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $hdrs, 'cookies' => $cookies, 'body' => substr($raw, $hsize)];
}

function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  OK    $label\n";
    } else {
        $failed++;
        echo "  ÉCHEC $label\n";
    }
}

function sessionId(array $r): ?string
{
    foreach ($r['cookies'] as $c) {
        if (preg_match('/^mpci_sess=([^;]+)/', $c, $m)) {
            return $m[1];
        }
    }
    return null;
}

function csrfFrom(string $html): string
{
    preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $html, $m);
    return $m[1] ?? '';
}

function spamTs(): string
{
    $ts = (string) (time() - 10);
    return $ts . '.' . hash_hmac('sha256', 'form|' . $ts, app_key());
}

echo "Tests de sécurité HTTP — $base\n" . str_repeat('─', 72) . "\n";
$home = http('GET', "$base/");
if ($home['status'] !== 200) {
    fwrite(STDERR, "Serveur injoignable ($base). Lancez : php -S localhost:8000 -t public public/router.php\n");
    exit(2);
}

echo "En-têtes\n";
foreach (['/' => 200, '/page-inexistante' => 404, '/admin/connexion' => 200, '/commande/estimation?zone_id=1' => 200] as $path => $code) {
    $r = http('GET', $base . $path);
    check($r['status'] === $code, "$path → $code");
    check(!isset($r['headers']['x-powered-by']), "$path : pas de X-Powered-By");
    check(str_contains($r['headers']['content-security-policy'] ?? '', "script-src 'self'") && str_contains($r['headers']['content-security-policy'] ?? '', "frame-ancestors 'none'"), "$path : CSP stricte");
    check(($r['headers']['x-frame-options'] ?? '') === 'DENY' && ($r['headers']['x-content-type-options'] ?? '') === 'nosniff', "$path : DENY + nosniff");
    check(($r['headers']['referrer-policy'] ?? '') === 'strict-origin-when-cross-origin', "$path : Referrer-Policy");
    check(!preg_match('/(Warning|Notice|Fatal error|Deprecated|Stack trace)/', $r['body']), "$path : aucun message PHP");
}
$priv = http('GET', "$base/compte/connexion");
check(str_contains($priv['headers']['cache-control'] ?? '', 'no-store'), 'Zone compte : no-store');

echo "Fichiers et traversée de répertoires\n";
foreach (['/.env', '/../.env', '/..%2f.env', '/%2e%2e/.env', '/assets/../../.env', '/assets/%2e%2e/%2e%2e/.env', '/assets/..%5c..%5c.env', '/index.php', '/router.php',
    '/uploads/.htaccess', '/.htaccess', '/../app/bootstrap.php', '/assets/../../app/config/defaults.php', '/../storage/logs/', '/../composer.json', '/.git/config', '/uploads/'] as $path) {
    $r = http('GET', $base . $path);
    check($r['status'] === 404 && !str_contains($r['body'], 'APP_KEY') && !str_contains($r['body'], '<?php'), "$path → 404 sans contenu sensible (obtenu {$r['status']})");
}
check(http('GET', "$base/assets/css/app.css")['status'] === 200, 'Fichier statique légitime servi');

echo "Méthodes HTTP\n";
foreach (['TRACE', 'PUT', 'DELETE', 'PATCH'] as $m) {
    $r = http($m, "$base/");
    check(in_array($r['status'], [404, 405, 419], true), "$m / → {$r['status']}");
}

echo "Sessions et cookies\n";
$fixed = http('GET', "$base/compte/connexion", [], null, 'mpci_sess=attaquantfixe1234567890abcdef');
$sid = sessionId($fixed);
check($sid !== null && $sid !== 'attaquantfixe1234567890abcdef', 'Identifiant de session imposé refusé (mode strict)');
$cookieLine = implode(' | ', $fixed['cookies']);
check(str_contains($cookieLine, 'HttpOnly') && str_contains($cookieLine, 'SameSite=Lax'), 'Cookie de session HttpOnly + SameSite=Lax');

// Compte jetable créé par inscription HTTP.
$email = 'sec-' . bin2hex(random_bytes(4)) . '@example.test';
$pwd = 'Securite' . random_int(1000, 9999) . 'x';
$form = http('GET', "$base/compte/inscription");
$s0 = sessionId($form);
$reg = http('POST', "$base/compte/inscription", ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
    '_csrf' => csrfFrom($form['body']), '_ts' => spamTs(), 'website' => '', 'first_name' => 'Sec', 'last_name' => 'Test', 'email' => $email,
    'password' => $pwd, 'password_confirmation' => $pwd, 'consent' => '1']), "mpci_sess=$s0");
$s1 = sessionId($reg);
check($reg['status'] === 303 && $s1 !== null && $s1 !== $s0, 'Nouvel identifiant de session à la connexion (anti-fixation)');
$old = http('GET', "$base/compte", [], null, "mpci_sess=$s0");
check($old['status'] === 303, 'Ancien identifiant de session inutilisable');
$me = http('GET', "$base/compte", [], null, "mpci_sess=$s1");
check($me['status'] === 200, 'Nouvelle session authentifiée');

echo "CSRF\n";
$r = http('POST', "$base/compte/deconnexion", ['Content-Type: application/x-www-form-urlencoded'], '', "mpci_sess=$s1");
check($r['status'] === 419, 'POST sans jeton → 419');
$other = http('GET', "$base/contact");
$foreign = csrfFrom($other['body']);
$r = http('POST', "$base/compte/deconnexion", ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['_csrf' => $foreign]), "mpci_sess=$s1");
check($r['status'] === 419, 'Jeton d\'une autre session → 419');
check(http('GET', "$base/compte", [], null, "mpci_sess=$s1")['status'] === 200, 'Toujours connecté (pas de déconnexion forcée)');

echo "En-tête Host (empoisonnement des liens de réinitialisation)\n";
$f = http('GET', "$base/compte/mot-de-passe-oublie");
$sf = sessionId($f);
$before = glob(BASE_PATH . '/storage/mail/*.eml') ?: [];
http('POST', "$base/compte/mot-de-passe-oublie", ['Content-Type: application/x-www-form-urlencoded', 'Host: evil.example', 'X-Forwarded-Host: evil.example'],
    http_build_query(['_csrf' => csrfFrom($f['body']), 'email' => $email]), "mpci_sess=$sf");
$new = array_values(array_diff(glob(BASE_PATH . '/storage/mail/*.eml') ?: [], $before));
$mail = $new ? (string) file_get_contents(end($new)) : '';
check($mail !== '' && !str_contains($mail, 'evil.example') && str_contains($mail, base_url() . '/compte/reinitialiser/'), 'Lien construit depuis APP_URL, pas depuis Host');

echo "Force brute\n";
$lf = http('GET', "$base/compte/connexion");
$sl = sessionId($lf);
$token = csrfFrom($lf['body']);
for ($i = 0; $i < 5; $i++) {
    http('POST', "$base/compte/connexion", ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['_csrf' => $token, 'email' => $email, 'password' => "faux$i"]), "mpci_sess=$sl");
}
$r = http('POST', "$base/compte/connexion", ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['_csrf' => $token, 'email' => $email, 'password' => $pwd]), "mpci_sess=$sl");
check($r['status'] === 422 && str_contains($r['body'], 'Trop de tentatives'), 'Verrouillage après 5 échecs (bon mot de passe refusé)');

echo "Robustesse\n";
$r = http('GET', "$base/boutique?q=" . str_repeat('A', 8000));
check($r['status'] < 500, 'Paramètre très long sans erreur 500 (' . $r['status'] . ')');
$r = http('GET', "$base/boutique?q[]=x&categorie[]=y&page[]=1");
check($r['status'] < 500, 'Paramètres tableau inattendus sans erreur 500 (' . $r['status'] . ')');
$r = http('POST', "$base/webhooks/paiement/demo", ['Content-Type: application/json'], '{"event_id":');
check(in_array($r['status'], [400, 401], true) && str_contains($r['headers']['content-type'] ?? '', 'json'), 'Webhook mal formé → 400 JSON');
$r = http('GET', "$base/boutique/%00");
check(in_array($r['status'], [400, 404], true), 'Octet nul dans l\'URL rejeté (' . $r['status'] . ')');

echo str_repeat('─', 72) . "\n$passed contrôle(s) réussi(s), $failed échec(s)\n";
exit($failed > 0 ? 1 : 0);
