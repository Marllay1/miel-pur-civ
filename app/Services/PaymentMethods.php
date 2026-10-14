<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Lang;

/**
 * Moyens de paiement administrables (table payment_methods) : source unique pour le paiement (checkout),
 * les logos affichés (pied de page, panier, accueil) et l'administration.
 *
 * Un moyen n'est présenté aux clients que s'il est OPÉRATIONNEL :
 *  - cod (à la livraison)   : activé ;
 *  - gateway (prestataire)  : activé ET clés du prestataire présentes dans .env (intégration technique réelle) ;
 *  - manual (paiement hors site, confirmé par l'équipe) : activé ET numéro de réception ou instructions renseignés ;
 *  - invoice (sur facture) : activé, et proposé uniquement aux clients professionnels autorisés par un administrateur.
 * La présence d'un logo ne vaut jamais intégration : seuls les moyens opérationnels affichent leur logo.
 */
final class PaymentMethods
{
    public const KINDS = ['cod' => 'Paiement à la livraison', 'gateway' => 'Prestataire intégré', 'manual' => 'Paiement manuel (confirmé par l\'équipe)', 'invoice' => 'Paiement sur facture (professionnels autorisés)'];

    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return array<string, array> toutes les lignes, indexées par code, dans l'ordre d'affichage */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (DB::all('SELECT * FROM payment_methods ORDER BY sort_order, id') as $row) {
                self::$cache[$row['code']] = $row;
            }
        }
        return self::$cache;
    }

    public static function find(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    public static function name(array $m): string
    {
        return Lang::get() === 'en' && (string) $m['name_en'] !== '' ? (string) $m['name_en'] : (string) $m['name'];
    }

    public static function description(array $m): string
    {
        $v = Lang::get() === 'en' && (string) $m['description_en'] !== '' ? (string) $m['description_en'] : (string) $m['description'];
        return $v;
    }

    public static function instructions(array $m): string
    {
        return Lang::get() === 'en' && (string) $m['instructions_en'] !== '' ? (string) $m['instructions_en'] : (string) $m['instructions'];
    }

    /** @return string[] chemins des logos à afficher (vide si masqués) */
    public static function logos(array $m): array
    {
        if ((int) $m['show_logo'] !== 1) {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $m['logo_paths']) ?: [])));
    }

    /** Clés du prestataire présentes (moyens « gateway »). */
    public static function isConfigured(array $m): bool
    {
        if ($m['kind'] !== 'gateway') {
            return true;
        }
        $gateway = PaymentService::gateways()[$m['code']] ?? null;
        return $gateway !== null && $gateway->isConfigured();
    }

    public static function isOperational(array $m): bool
    {
        if ((int) $m['is_enabled'] !== 1) {
            return false;
        }
        return match ($m['kind']) {
            'cod' => true,
            'gateway' => self::isConfigured($m),
            'manual' => trim((string) $m['receiver_number']) !== '' || trim((string) $m['instructions']) !== '',
            'invoice' => true,
            default => false,
        };
    }

    /** @return array{0:string,1:string} [état, libellé] pour l'administration */
    public static function status(array $m): array
    {
        if ((int) $m['is_enabled'] !== 1) {
            return ['off', 'Désactivé — non proposé'];
        }
        if ($m['kind'] === 'gateway' && !self::isConfigured($m)) {
            return ['missing', 'Activé, mais clés du prestataire manquantes — non proposé'];
        }
        if ($m['kind'] === 'manual' && !self::isOperational($m)) {
            return ['missing', 'Activé, mais numéro ou instructions manquants — non proposé'];
        }
        return ['ok', match ($m['kind']) {
            'manual' => 'Proposé — paiement manuel, à confirmer par l\'équipe',
            'invoice' => 'Proposé aux seuls professionnels autorisés (Clients professionnels)',
            default => 'Proposé aux clients',
        }];
    }

    /** @return array<string, array> moyens opérationnels, dans l'ordre */
    public static function operational(): array
    {
        return array_filter(self::all(), [self::class, 'isOperational']);
    }

    /** Montant de commande dans les limites du moyen (minimum / maximum, vides = sans limite). */
    public static function allowsAmount(array $m, int $total): bool
    {
        return ($m['min_amount'] === null || $total >= (int) $m['min_amount']) && ($m['max_amount'] === null || $total <= (int) $m['max_amount']);
    }

    /** Logos des moyens réellement proposés (pied de page, panier, accueil) : [[nom, [logos]], …]. */
    public static function acceptedLogos(): array
    {
        $out = [];
        foreach (self::operational() as $m) {
            if ($m['kind'] === 'invoice') {
                continue;
            }
            $out[] = ['code' => $m['code'], 'name' => self::name($m), 'logos' => self::logos($m)];
        }
        return $out;
    }
}
