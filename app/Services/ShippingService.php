<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class ShippingService
{
    public static function zones(bool $activeOnly = true): array
    {
        return I18n::localize('zone', DB::all('SELECT * FROM shipping_zones' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, name'));
    }

    public static function find(int $id, bool $activeOnly = true): ?array
    {
        return I18n::localizeOne('zone', DB::one('SELECT * FROM shipping_zones WHERE id = ?' . ($activeOnly ? ' AND is_active = 1' : ''), [$id]));
    }

    /** Frais de livraison, calculés sur le montant après remise. */
    public static function fee(array $zone, int $amountAfterDiscount, bool $freeShipping = false): int
    {
        if ($freeShipping) {
            return 0;
        }
        if ($zone['free_threshold'] !== null && (int) $zone['free_threshold'] > 0 && $amountAfterDiscount >= (int) $zone['free_threshold']) {
            return 0;
        }
        return (int) $zone['fee'];
    }
}
