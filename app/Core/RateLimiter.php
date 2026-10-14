<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitation de fréquence persistée en base (table rate_limits, bucket = sha1 de la clé).
 */
final class RateLimiter
{
    private static function bucket(string $key): string
    {
        return substr(sha1($key), 0, 40);
    }

    /** Enregistre une tentative ; renvoie false si la limite est déjà atteinte. */
    public static function hit(string $key, int $max, int $decaySeconds): bool
    {
        $bucket = self::bucket($key);
        $row = DB::one('SELECT hits, reset_at FROM rate_limits WHERE bucket = ?', [$bucket]);
        if ($row === null || strtotime($row['reset_at']) <= time()) {
            DB::run('DELETE FROM rate_limits WHERE bucket = ?', [$bucket]);
            DB::insert('rate_limits', ['bucket' => $bucket, 'hits' => 1, 'reset_at' => gmdate('Y-m-d H:i:s', time() + $decaySeconds)]);
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        DB::run('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$bucket]);
        return true;
    }

    public static function tooMany(string $key, int $max): bool
    {
        $row = DB::one('SELECT hits, reset_at FROM rate_limits WHERE bucket = ?', [self::bucket($key)]);
        return $row !== null && strtotime($row['reset_at']) > time() && (int) $row['hits'] >= $max;
    }

    public static function clear(string $key): void
    {
        DB::run('DELETE FROM rate_limits WHERE bucket = ?', [self::bucket($key)]);
    }

    public static function purgeExpired(): int
    {
        return DB::run('DELETE FROM rate_limits WHERE reset_at < ?', [gmdate('Y-m-d H:i:s')])->rowCount();
    }
}
