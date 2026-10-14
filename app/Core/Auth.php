<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentification et contrôle d'accès par permissions (toujours vérifié côté serveur).
 */
final class Auth
{
    public const STAFF_IDLE_SECONDS = 7200;
    public const MAX_PER_ACCOUNT = 5;
    public const MAX_PER_IP = 20;
    public const WINDOW = 900;

    private static ?array $user = null;
    private static bool $loaded = false;
    private static ?array $permissions = null;
    public static bool $expired = false;

    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
        self::$permissions = null;
        self::$expired = false;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = Session::get('user_id');
        if (!is_int($id)) {
            return null;
        }
        $user = DB::one(
            'SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1',
            [$id]
        );
        if ($user === null) {
            Session::forget('user_id');
            return null;
        }
        // Mot de passe changé ailleurs (réinitialisation, autre appareil) : cette session n'est plus valide.
        if (!hash_equals(self::fingerprint($user['password_hash']), (string) Session::get('pwd_fp', ''))) {
            self::logout();
            return null;
        }
        if ($user['role'] !== 'customer') {
            $seen = (int) Session::get('staff_seen', 0);
            if ($seen > 0 && time() - $seen > self::STAFF_IDLE_SECONDS) {
                self::logout();
                self::$expired = true;
                return null;
            }
            Session::set('staff_seen', time());
        }
        self::$user = $user;
        return $user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isStaff(): bool
    {
        $u = self::user();
        return $u !== null && $u['role'] !== 'customer';
    }

    /** @return string[] */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $u = self::user();
        if ($u === null) {
            return [];
        }
        self::$permissions = array_column(DB::all(
            'SELECT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?',
            [(int) $u['role_id']]
        ), 'name');
        return self::$permissions;
    }

    public static function can(string $permission): bool
    {
        return self::isStaff() && in_array($permission, self::permissions(), true);
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::forget('_csrf');
        Session::set('user_id', (int) $user['id']);
        self::rememberPassword((string) DB::value('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]));
        Session::set('auth_at', time());
        Session::set('staff_seen', time());
        DB::update('users', ['last_login_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $user['id']]);
        self::reset();
    }

    public static function fingerprint(string $passwordHash): string
    {
        return hash_hmac('sha256', $passwordHash, (string) Env::get('APP_KEY', ''));
    }

    /** À appeler après un changement de mot de passe pour garder la session courante valide. */
    public static function rememberPassword(string $passwordHash): void
    {
        Session::set('pwd_fp', self::fingerprint($passwordHash));
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $cart = Session::get('cart_token');
        Session::destroy();
        if ($cart) {
            Session::set('cart_token', $cart);
        }
        self::reset();
    }

    /**
     * Vérifie identifiants + limitation (5 essais / 15 min par e-mail+IP, 20 par IP).
     * @return array{ok:bool, user:?array, error:?string}
     */
    public static function attempt(string $email, string $password, string $ip, bool $staffOnly = false): array
    {
        $email = mb_strtolower(trim($email));
        $kAccount = 'login:' . $email . '|' . $ip;
        $kIp = 'login-ip:' . $ip;
        if (RateLimiter::tooMany($kAccount, self::MAX_PER_ACCOUNT) || RateLimiter::tooMany($kIp, self::MAX_PER_IP)) {
            return ['ok' => false, 'user' => null, 'error' => 'Trop de tentatives. Réessayez dans 15 minutes.'];
        }
        $user = DB::one(
            'SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?',
            [$email]
        );
        // Vérification exécutée même si le compte n'existe pas (temps constant).
        static $dummy = null;
        $dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $valid = password_verify($password, $user['password_hash'] ?? $dummy);
        $allowed = $user !== null && $valid && (int) $user['is_active'] === 1 && (!$staffOnly || $user['role'] !== 'customer');
        if (!$allowed) {
            RateLimiter::hit($kAccount, self::MAX_PER_ACCOUNT, self::WINDOW);
            RateLimiter::hit($kIp, self::MAX_PER_IP, self::WINDOW);
            return ['ok' => false, 'user' => null, 'error' => 'Identifiants incorrects.'];
        }
        RateLimiter::clear($kAccount);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [(int) $user['id']]);
        }
        return ['ok' => true, 'user' => $user, 'error' => null];
    }
}
