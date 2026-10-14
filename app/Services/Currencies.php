<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Lang;

/**
 * Devises d'affichage (Administration → Paramètres du site → Devises).
 * Le prix de vente et le paiement restent TOUJOURS en francs CFA (XOF) : les montants convertis sont indicatifs,
 * calculés avec le taux saisi par l'administrateur (aucune conversion automatique ni géolocalisation).
 */
final class Currencies
{
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function all(): array
    {
        return DB::all('SELECT * FROM currencies ORDER BY sort_order, id');
    }

    /** @return array<int, array> devises activées avec un taux renseigné */
    public static function enabled(): array
    {
        if (self::$cache === null) {
            self::$cache = array_values(array_filter(self::all(), static fn ($c) => (int) $c['is_enabled'] === 1 && (float) $c['xof_per_unit'] > 0));
        }
        return self::$cache;
    }

    /** Montant converti et formaté selon la langue : « 6,86 € » (fr) ou « €6.86 » (en). */
    public static function format(int $xof, array $c): string
    {
        $value = $xof / (float) $c['xof_per_unit'];
        $decimals = (int) $c['decimals'];
        if (Lang::get() === 'en') {
            return $c['symbol'] . number_format($value, $decimals, '.', ',');
        }
        return number_format($value, $decimals, ',', "\u{202F}") . "\u{00A0}" . $c['symbol'];
    }

    /** @return string[] conversions indicatives d'un montant en FCFA (vide si aucune devise activée) */
    public static function conversions(int $xof): array
    {
        return array_map(static fn ($c) => '≈ ' . self::format($xof, $c), self::enabled());
    }
}
