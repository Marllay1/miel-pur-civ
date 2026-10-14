<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\RateLimiter;

final class AccountService
{
    public const MIN_PASSWORD = 10;

    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return t('password.min', ['min' => self::MIN_PASSWORD]);
        }
        if (mb_strlen($password) > 200) {
            return t('password.too_long');
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return t('password.letters_digits');
        }
        return null;
    }

    public static function roleId(string $name): int
    {
        $id = DB::value('SELECT id FROM roles WHERE name = ?', [$name]);
        if ($id === null) {
            throw new \RuntimeException("Rôle manquant : $name (lancer bin/install.php)");
        }
        return (int) $id;
    }

    /** @return array{ok:bool, user?:array, error?:string} */
    public static function register(array $data, string $role = 'customer'): array
    {
        $email = mb_strtolower(trim($data['email']));
        if (DB::value('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            return ['ok' => false, 'error' => t('account.exists_long')];
        }
        $now = gmdate('Y-m-d H:i:s');
        try {
            $id = DB::insert('users', [
                'role_id' => self::roleId($role),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => $data['phone'] ?? null ?: null,
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'is_active' => 1,
                'marketing_opt_in' => !empty($data['marketing_opt_in']) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\PDOException $e) {
            if (DB::isUniqueViolation($e)) {
                return ['ok' => false, 'error' => t('account.exists')];
            }
            throw $e;
        }
        return ['ok' => true, 'user' => DB::one('SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id])];
    }

    /** Envoie un lien si le compte existe. Réponse identique dans tous les cas (pas d'énumération). */
    public static function requestReset(string $email, string $ip): void
    {
        $email = mb_strtolower(trim($email));
        if (!RateLimiter::hit('reset:' . $ip, 5, 3600) || !RateLimiter::hit('reset-mail:' . $email, 3, 3600)) {
            return;
        }
        $user = DB::one('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
        if ($user === null) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        DB::run('DELETE FROM password_resets WHERE user_id = ?', [(int) $user['id']]);
        DB::insert('password_resets', [
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'used_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        Notifier::passwordReset($user, $token, \App\Core\Lang::get());
    }

    public static function findReset(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return DB::one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?', [hash('sha256', $token), gmdate('Y-m-d H:i:s')]);
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $reset = self::findReset($token);
        if ($reset === null) {
            return false;
        }
        return DB::transaction(static function () use ($reset, $password): bool {
            $n = DB::run('UPDATE password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL', [gmdate('Y-m-d H:i:s'), (int) $reset['id']])->rowCount();
            if ($n !== 1) {
                return false;
            }
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $reset['user_id']]);
            return true;
        });
    }

    /** Achat vérifié : commande livrée contenant ce produit. */
    public static function hasPurchased(int $userId, int $productId): bool
    {
        return DB::value("SELECT 1 FROM orders o JOIN order_items i ON i.order_id = o.id WHERE o.user_id = ? AND i.product_id = ? AND o.status = 'delivered' LIMIT 1", [$userId, $productId]) !== null;
    }
}
