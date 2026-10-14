<?php
declare(strict_types=1);

/**
 * Outils de test sans dépendance : base fraîche, client HTTP en processus (cookies + session), assertions.
 */

use App\Core\App;
use App\Core\Auth;
use App\Core\Cookie;
use App\Core\DB;
use App\Core\Env;
use App\Core\Mailer;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AccountService;
use App\Services\Settings;

final class AssertionFailed extends \RuntimeException
{
}

final class T
{
    public static string $driver = 'sqlite';
    public static int $assertions = 0;
    private static bool $mysqlMigrated = false;
    /** Données de référence insérées par les migrations (vidées par TRUNCATE entre scénarios MySQL). */
    private const REFERENCE_TABLES = ['payment_methods', 'home_slides', 'currencies', 'media', 'home_highlights'];
    /** @var array<string, array> */
    private static array $reference = [];

    public static function fresh(): void
    {
        Env::set('APP_ENV', 'testing');
        Env::set('APP_DEBUG', '1');
        Env::set('APP_URL', 'http://localhost');
        Env::set('APP_KEY', str_repeat('ab', 32));
        Env::set('MAIL_DRIVER', 'log');
        Env::set('DEMO_PAYMENT_ENABLED', '1');
        Env::set('DEMO_PAYMENT_SECRET', str_repeat('5e', 32));
        Env::set('ANTISPAM_MIN_SECONDS', '0');
        self::clearProviders();
        \App\Core\Lang::set('fr');
        if (self::$driver === 'sqlite') {
            DB::setPdo(DB::connect(['driver' => 'sqlite', 'database' => ':memory:']));
            Migrator::migrate();
        } else {
            if (!self::$mysqlMigrated) {
                DB::setPdo(DB::connect(['driver' => 'mysql', 'host' => Env::get('TEST_DB_HOST', '127.0.0.1'), 'port' => Env::get('TEST_DB_PORT', '3306'),
                    'database' => Env::get('TEST_DB_DATABASE', 'miel_test'), 'username' => Env::get('TEST_DB_USERNAME', 'miel'), 'password' => Env::get('TEST_DB_PASSWORD', '')]));
                DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach (DB::all('SHOW TABLES') as $t) {
                    DB::pdo()->exec('DROP TABLE `' . array_values($t)[0] . '`');
                }
                DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
                Migrator::migrate();
                foreach (self::REFERENCE_TABLES as $t) {
                    self::$reference[$t] = DB::all("SELECT * FROM `$t`");
                }
                self::$mysqlMigrated = true;
            } else {
                DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach (DB::all('SHOW TABLES') as $t) {
                    $name = array_values($t)[0];
                    if ($name !== 'migrations') {
                        DB::pdo()->exec("TRUNCATE TABLE `$name`");
                    }
                }
                DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
                foreach (self::$reference as $t => $rows) {
                    foreach ($rows as $row) {
                        DB::run("INSERT INTO `$t` (`" . implode('`, `', array_keys($row)) . '`) VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')', array_values($row));
                    }
                }
            }
        }
        (require BASE_PATH . '/database/seeds/base.php')();
        (require BASE_PATH . '/database/seeds/catalog.php')();
        DB::run("UPDATE promo_codes SET is_active = 1 WHERE code = 'BIENVENUE10'");
        DB::run("INSERT INTO settings (name, value, updated_at) VALUES ('demo_payment_enabled', '1', ?)", [gmdate('Y-m-d H:i:s')]);
        \App\Core\Lang::set('fr');
        Settings::flush();
        \App\Core\Lang::flushOverrides();
        \App\Services\PaymentMethods::flush();
        \App\Services\Currencies::flush();
        Mailer::$sent = [];
        Cookie::$queued = [];
        Session::fake();
        Auth::reset();
    }

    /** @var resource|null */
    private static $mock = null;
    public const MOCK = 'http://127.0.0.1:8099';

    /** Démarre le simulateur des prestataires (une fois) et configure les quatre passerelles pour l'utiliser. */
    public static function providers(): void
    {
        if (self::$mock === null) {
            $php = PHP_BINARY;
            $cmd = '"' . $php . '" -S 127.0.0.1:8099 "' . BASE_PATH . '/tests/mock-providers/server.php"';
            self::$mock = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/mpci-mock.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/mpci-mock.log', 'a']], $pipes);
            register_shutdown_function(static function (): void {
                if (is_resource(self::$mock)) {
                    $status = proc_get_status(self::$mock);
                    if (PHP_OS_FAMILY === 'Windows') {
                        exec('taskkill /F /T /PID ' . (int) $status['pid'] . ' 2>NUL');
                    }
                    proc_terminate(self::$mock);
                }
            });
            for ($i = 0; $i < 50; $i++) {
                $fp = @fsockopen('127.0.0.1', 8099, $errno, $errstr, 0.2);
                if ($fp) {
                    fclose($fp);
                    break;
                }
                usleep(100000);
            }
        }
        \App\Core\HttpClient::request('POST', self::MOCK . '/_control/reset', [], '');
        foreach (glob(BASE_PATH . '/storage/cache/token-*') ?: [] as $f) {
            @unlink($f);
        }
        $env = [
            'WAVE_API_BASE' => self::MOCK . '/wave', 'WAVE_API_KEY' => 'wave_test_key', 'WAVE_WEBHOOK_SECRET' => 'wave_webhook_secret_for_tests',
            'MTN_MOMO_API_BASE' => self::MOCK . '/mtn', 'MTN_MOMO_SUBSCRIPTION_KEY' => 'mtn_sub_key', 'MTN_MOMO_API_USER' => 'mtn_api_user',
            'MTN_MOMO_API_KEY' => 'mtn_api_key', 'MTN_MOMO_TARGET_ENV' => 'sandbox', 'MTN_MOMO_CURRENCY' => 'XOF',
            'ORANGE_MONEY_API_BASE' => self::MOCK . '/orange', 'ORANGE_MONEY_CLIENT_ID' => 'om_client', 'ORANGE_MONEY_CLIENT_SECRET' => 'om_secret',
            'ORANGE_MONEY_MERCHANT_KEY' => 'om_merchant_key', 'ORANGE_MONEY_PATH' => '/orange-money-webpay/dev/v1',
            'CINETPAY_API_BASE' => self::MOCK . '/cinetpay', 'CINETPAY_API_KEY' => 'cp_api_key', 'CINETPAY_SITE_ID' => '123456', 'CINETPAY_SECRET_KEY' => 'cp_secret',
        ];
        foreach ($env as $k => $v) {
            Env::set($k, $v);
        }
    }

    /** Simule l'action du client chez le prestataire (validation, refus…). */
    public static function providerSet(string $provider, string $id, string $status, ?int $amount = null): void
    {
        \App\Core\HttpClient::request('POST', self::MOCK . '/_control/' . $provider . '/' . rawurlencode($id) . '/' . $status . ($amount !== null ? '?amount=' . $amount : ''), [], '');
    }

    public static function clearProviders(): void
    {
        foreach (['WAVE_API_KEY', 'WAVE_WEBHOOK_SECRET', 'MTN_MOMO_SUBSCRIPTION_KEY', 'MTN_MOMO_API_USER', 'MTN_MOMO_API_KEY', 'MTN_MOMO_TARGET_ENV',
            'ORANGE_MONEY_CLIENT_ID', 'ORANGE_MONEY_CLIENT_SECRET', 'ORANGE_MONEY_MERCHANT_KEY', 'ORANGE_MONEY_PATH', 'CINETPAY_API_KEY', 'CINETPAY_SITE_ID', 'CINETPAY_SECRET_KEY'] as $k) {
            Env::set($k, '');
        }
    }

    public static function user(string $role, string $email, string $password = 'Motdepasse123'): array
    {
        $res = AccountService::register(['first_name' => ucfirst($role), 'last_name' => 'Test', 'email' => $email, 'password' => $password, 'phone' => '0700000000'], $role);
        if (!$res['ok']) {
            throw new AssertionFailed('Création utilisateur impossible : ' . $res['error']);
        }
        return $res['user'];
    }

    public static function variant(string $sku): array
    {
        $v = DB::one('SELECT * FROM product_variants WHERE sku = ?', [$sku]);
        if ($v === null) {
            throw new AssertionFailed("Variante $sku introuvable");
        }
        return $v;
    }

    public static function zone(string $name): array
    {
        return DB::one('SELECT * FROM shipping_zones WHERE name = ?', [$name]);
    }

    public static function stock(string $sku): int
    {
        return (int) DB::value('SELECT stock FROM product_variants WHERE sku = ?', [$sku]);
    }

    public static function spamOk(): array
    {
        $ts = (string) (time() - 10);
        return ['_ts' => $ts . '.' . hash_hmac('sha256', 'form|' . $ts, app_key()), 'website' => ''];
    }

    // ---- Assertions
    public static function ok(bool $cond, string $message): void
    {
        self::$assertions++;
        if (!$cond) {
            throw new AssertionFailed($message);
        }
    }

    public static function eq(mixed $expected, mixed $actual, string $message): void
    {
        self::ok($expected === $actual, $message . ' (attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true) . ')');
    }

    public static function status(int $expected, Response $r, string $message): void
    {
        $extra = '';
        if ($r->status !== $expected) {
            preg_match('#<p class="lead">(.*?)</p>#s', $r->body, $m);
            $extra = ' — ' . trim(html_entity_decode(strip_tags($m[1] ?? substr($r->body, 0, 200))));
        }
        self::eq($expected, $r->status, $message . $extra);
    }

    /** Remplace les espaces insécables (fine et normale) utilisées par money() par des espaces simples. */
    public static function plain(string $s): string
    {
        return str_replace(["\u{202F}", "\u{00A0}"], ' ', $s);
    }

    public static function contains(string $needle, string $haystack, string $message): void
    {
        self::ok(str_contains(self::plain($haystack), $needle), $message . " (texte « $needle » absent)");
    }

    public static function notContains(string $needle, string $haystack, string $message): void
    {
        self::ok(!str_contains($haystack, $needle), $message . " (texte « $needle » présent)");
    }

    public static function redirectsTo(string $prefix, Response $r, string $message): void
    {
        self::ok(in_array($r->status, [302, 303], true) && str_starts_with($r->headers['Location'] ?? '', $prefix),
            $message . ' (statut ' . $r->status . ', Location ' . ($r->headers['Location'] ?? '—') . ')');
    }
}

/** Navigateur simulé : cookies et session propres, passe par App::handle (routage, middlewares, CSRF, vues). */
final class Client
{
    public array $cookies = [];
    public array $session = [];

    public function __construct(public string $ip = '10.0.0.1')
    {
    }

    public function request(string $method, string $uri, array $post = [], array $headers = [], string $body = ''): Response
    {
        $u = parse_url($uri);
        parse_str($u['query'] ?? '', $query);
        Session::fake($this->session);
        Cookie::$queued = [];
        $request = new Request($method, $u['path'], $query, $post, $this->cookies, [], ['REMOTE_ADDR' => $this->ip], $body, $headers);
        $response = App::handle($request);
        $this->session = Session::all();
        foreach (Cookie::$queued as $name => $c) {
            if ($c['expires'] !== 0 && $c['expires'] < time()) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $c['value'];
            }
        }
        Cookie::$queued = [];
        return $response;
    }

    /** Envoi de formulaire avec fichiers ($files : champ => chemin local), comme un navigateur (CSRF inclus). */
    public function upload(string $uri, array $post, array $files): Response
    {
        $post['_csrf'] = $this->csrf();
        $f = [];
        foreach ($files as $field => $path) {
            $f[$field] = ['name' => basename($path), 'type' => 'application/octet-stream', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
        }
        $u = parse_url($uri);
        Session::fake($this->session);
        Cookie::$queued = [];
        $response = App::handle(new Request('POST', $u['path'], [], $post, $this->cookies, $f, ['REMOTE_ADDR' => $this->ip], '', []));
        $this->session = Session::all();
        foreach (Cookie::$queued as $name => $c) {
            $this->cookies[$name] = $c['value'];
        }
        Cookie::$queued = [];
        return $response;
    }

    public function get(string $uri, array $headers = []): Response
    {
        return $this->request('GET', $uri, [], $headers);
    }

    public function csrf(): string
    {
        if (empty($this->session['_csrf'])) {
            $this->session['_csrf'] = bin2hex(random_bytes(32));
        }
        return $this->session['_csrf'];
    }

    public function post(string $uri, array $data = [], bool $withCsrf = true, array $headers = []): Response
    {
        if ($withCsrf) {
            $data['_csrf'] = $this->csrf();
        }
        return $this->request('POST', $uri, $data, $headers);
    }

    public function follow(Response $r): Response
    {
        $hops = 0;
        while (in_array($r->status, [302, 303], true) && $hops++ < 5) {
            $r = $this->get($r->headers['Location']);
        }
        return $r;
    }

    public function loginAs(array $user): void
    {
        $this->session['user_id'] = (int) $user['id'];
        $this->session['staff_seen'] = time();
        $this->session['pwd_fp'] = Auth::fingerprint((string) DB::value('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]));
    }

    public function addToCart(string $sku, int $qty): Response
    {
        return $this->post('/panier/ajouter', ['variant_id' => (string) T::variant($sku)['id'], 'qty' => (string) $qty]);
    }

    /** Parcours de commande complet ; $override permet de falsifier des champs. */
    public function checkout(string $zone = 'Abidjan', string $method = 'cod', array $override = []): Response
    {
        $form = $this->get('/commande');
        if ($form->status !== 200) {
            return $form;
        }
        $z = T::zone($zone);
        $est = json_decode($this->get('/commande/estimation?zone_id=' . $z['id'])->body, true);
        return $this->post('/commande', $override + [
            'idempotency_key' => (string) $this->session['checkout_key'],
            'expected_total' => (string) $est['total'],
            'first_name' => 'Awa', 'last_name' => 'Koné', 'email' => 'awa@example.test', 'phone' => '07 00 00 00 01',
            'zone_id' => (string) $z['id'], 'city' => 'Abidjan', 'district' => 'Cocody', 'address' => 'Rue des Jardins', 'landmark' => '',
            'payment_method' => $method, 'note' => '', 'cgv' => '1',
        ]);
    }

    /** Extrait numéro et jeton depuis la redirection de création. */
    public static function orderRef(Response $r): array
    {
        $loc = $r->headers['Location'] ?? '';
        // Paiement en ligne : redirection directe vers la page du prestataire (ici le paiement simulé).
        if (preg_match('#/paiement/demo/(PAY-[A-F0-9]+)\?t=([a-f0-9]{32})#', $loc, $p)) {
            $number = (string) DB::value('SELECT o.number FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.reference = ?', [$p[1]]);
            return [$number, $p[2]];
        }
        if (!preg_match('#/commande/(MPCI-[0-9]+-[A-F0-9]+)\?t=([a-f0-9]{32})#', $loc, $m)) {
            throw new AssertionFailed('Pas de redirection vers une commande (statut ' . $r->status . ', Location ' . ($r->headers['Location'] ?? '—') . ')');
        }
        return [$m[1], $m[2]];
    }
}
