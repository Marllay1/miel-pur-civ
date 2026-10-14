<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class PromoService
{
    public const TYPES = ['percent' => 'Pourcentage', 'fixed' => 'Montant fixe', 'free_shipping' => 'Livraison offerte'];

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($code)) ?? '');
    }

    public static function find(string $code): ?array
    {
        $code = self::normalize($code);
        return $code === '' ? null : DB::one('SELECT * FROM promo_codes WHERE code = ?', [$code]);
    }

    /**
     * @return array{valid:bool, error:?string, discount:int, free_shipping:bool}
     */
    public static function evaluate(?array $promo, int $subtotal, ?string $now = null): array
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $fail = static fn (string $msg) => ['valid' => false, 'error' => $msg, 'discount' => 0, 'free_shipping' => false];
        if ($promo === null || (int) $promo['is_active'] !== 1) {
            return $fail(t('promo.not_found'));
        }
        if ($promo['starts_at'] !== null && $promo['starts_at'] > $now) {
            return $fail(t('promo.not_started'));
        }
        if ($promo['ends_at'] !== null && $promo['ends_at'] < $now) {
            return $fail(t('promo.expired'));
        }
        if ($promo['max_uses'] !== null && (int) $promo['used_count'] >= (int) $promo['max_uses']) {
            return $fail(t('promo.exhausted'));
        }
        if ($subtotal < (int) $promo['min_subtotal']) {
            return $fail(t('promo.min', ['amount' => money((int) $promo['min_subtotal'])]));
        }
        $value = (int) $promo['value'];
        $discount = match ($promo['type']) {
            'percent' => intdiv($subtotal * max(0, min(100, $value)), 100),
            'fixed' => min($value, $subtotal),
            default => 0,
        };
        return ['valid' => true, 'error' => null, 'discount' => $discount, 'free_shipping' => $promo['type'] === 'free_shipping'];
    }

    /** Incrément atomique ; false si le plafond a été atteint entre-temps. */
    public static function consume(int $promoId): bool
    {
        return DB::run(
            'UPDATE promo_codes SET used_count = used_count + 1, updated_at = ? WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)',
            [gmdate('Y-m-d H:i:s'), $promoId]
        )->rowCount() === 1;
    }

    public static function release(int $promoId): void
    {
        DB::run('UPDATE promo_codes SET used_count = used_count - 1, updated_at = ? WHERE id = ? AND used_count > 0', [gmdate('Y-m-d H:i:s'), $promoId]);
    }
}
