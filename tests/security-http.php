<?php
declare(strict_types=1);

/**
 * Tests de sécurité en boîte noire contre un serveur HTTP réel.
 *   php -S localhost:8000 -t public public/router.php   (dans un autre terminal)
 *   php tests/security-http.php [http://localhost:8000]
 *   php tests/security-http.php http://miel.localhost        (Apache/XAMPP ; *.localhost est résolu vers 127.0.0.1)
 * Crée un compte client jetable dans la base configurée (.env) ; à lancer sur un environnement de développement,
 * avec MAIL_DRIVER=log (le contrôle de l'en-tête Host lit les messages dans storage/mail).
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
if (\App\Core\Env::get('APP_ENV') === 'production') {
    fwrite(STDERR, "Refusé : ce script crée des comptes et remet à zéro les limitations de fréquence (APP_ENV=production).\n");
    exit(2);
}
// Chaque exécution crée un compte et demande des réinitialisations : sans remise à zéro, les limitations
// (5 inscriptions / heure / IP…) feraient échouer la 2e ou 3e exécution. Elles sont testées par le scénario 25.
\App\Core\DB::run('DELETE FROM rate_limits');
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
    // *.localhost désigne toujours la machine locale (RFC 6761), même si le système ne le résout pas (Windows).
    $host = (string) parse_url($url, PHP_URL_HOST);
    if (str_ends_with($host, '.localhost')) {
        curl_setopt($ch, CURLOPT_RESOLVE, [$host . ':' . (parse_url($url, PHP_URL_PORT) ?: 80) . ':127.0.0.1']);
    }
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
    check(!preg_match('/\d+\.\d+/',$r['headers']['server'] ?? ''), "$path : en-tête Server sans numéro de version");
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
    // 404 (serveur PHP intégré, Nginx) ou 400/403 (Apache) : l'essentiel est que rien ne soit servi.
    check(in_array($r['status'], [400, 403, 404], true) && !str_contains($r['body'], 'APP_KEY') && !str_contains($r['body'], '<?php'), "$path → refusé sans contenu sensible (obtenu {$r['status']})");
}
check(http('GET', "$base/assets/css/app.css")['status'] === 200, 'Fichier statique légitime servi');

echo "Méthodes HTTP\n";
foreach (['TRACE', 'PUT', 'DELETE', 'PATCH'] as $m) {
    $r = http($m, "$base/");
    check(in_array($r['status'], [403, 404, 405], true), "$m / → {$r['status']}");
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
check($r['status'] === 403 && str_contains($r['body'], 'Formulaire expiré'), 'POST sans jeton → 403 « formulaire expiré »');
$other = http('GET', "$base/contact");
$foreign = csrfFrom($other['body']);
$r = http('POST', "$base/compte/deconnexion", ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['_csrf' => $foreign]), "mpci_sess=$s1");
check($r['status'] === 403, 'Jeton d\'une autre session → 403');
check(http('GET', "$base/compte", [], null, "mpci_sess=$s1")['status'] === 200, 'Toujours connecté (pas de déconnexion forcée)');

echo "En-tête Host (empoisonnement des liens de réinitialisation)\n";
/** Demande de réinitialisation avec des en-têtes forgés ; renvoie [statut HTTP, texte décodé du message produit ou ''] (MAIL_DRIVER=log). */
function resetRequest(string $base, string $email, array $headers): array
{
    $f = http('GET', "$base/compte/mot-de-passe-oublie");
    $before = glob(BASE_PATH . '/storage/mail/*.eml') ?: [];
    $r = http('POST', "$base/compte/mot-de-passe-oublie", array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        http_build_query(['_csrf' => csrfFrom($f['body']), 'email' => $email]), 'mpci_sess=' . sessionId($f));
    $new = array_values(array_diff(glob(BASE_PATH . '/storage/mail/*.eml') ?: [], $before));
    $raw = $new ? (string) file_get_contents(end($new)) : '';
    // Parties MIME encodées en base64 : on les décode pour lire les liens.
    $text = preg_replace_callback('/\n\n([A-Za-z0-9+\/=\n]{20,})(?=\n--)/', static fn ($m) => "\n\n" . base64_decode(str_replace("\n", '', $m[1])), $raw) ?? $raw;
    return [$r['status'], $text];
}
// 1) Host correct, X-Forwarded-Host forgé : l'application est atteinte et doit ignorer l'en-tête.
[, $mail] = resetRequest($base, $email, ['X-Forwarded-Host: evil.example', 'X-Forwarded-Proto: http']);
check($mail !== '' && !str_contains($mail, 'evil.example') && str_contains($mail, base_url() . '/compte/reinitialiser/'), 'X-Forwarded-Host ignoré : lien construit depuis APP_URL');
// 2) Host forgé : soit le serveur web ne transmet pas la requête (hôte virtuel inconnu), soit le lien vient d'APP_URL.
[$status, $mail] = resetRequest($base, $email, ['Host: evil.example']);
// Statut 0 : connexion fermée sans réponse (Nginx « return 444 » pour un hôte inconnu).
check($mail === '' ? ($status >= 400 || $status === 0) : (!str_contains($mail, 'evil.example') && str_contains($mail, base_url() . '/compte/reinitialiser/')),
    'Host forgé sans effet (' . ($mail === '' ? 'requête refusée par le serveur web : ' . ($status ?: 'connexion fermée') : 'lien construit depuis APP_URL') . ')');

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
