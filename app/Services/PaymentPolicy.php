<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Règles de paiement appliquées au moment de la commande, à partir des paramètres administrables
 * (Administration → Paramètres du site → Paramètres de paiement) et des caractéristiques de la commande :
 * type de client, destination, montant, commande en gros, acompte, autorisation de paiement sur facture.
 * Tous les montants sont calculés par le serveur.
 */
final class PaymentPolicy
{
    /** Compte professionnel actif du client, ou null. */
    public static function proAccount(?int $userId): ?array
    {
        if ($userId === null) {
            return null;
        }
        return DB::one('SELECT * FROM pro_accounts WHERE user_id = ? AND is_active = 1', [$userId]);
    }

    public static function customerType(?int $userId): string
    {
        return self::proAccount($userId) !== null ? 'professional' : 'individual';
    }

    /** Montant restant dû sur les commandes sur facture non soldées du client. */
    public static function outstanding(int $userId): int
    {
        return (int) DB::value(
            "SELECT COALESCE(SUM(total - amount_paid), 0) FROM orders WHERE user_id = ? AND payment_terms = 'invoice' AND status NOT IN ('cancelled', 'refunded') AND amount_paid < total",
            [$userId]
        );
    }

    /**
     * Paiement sur facture : seulement pour un professionnel explicitement autorisé par un administrateur,
     * dans la limite de son plafond de crédit (plafond vide = pas de plafond fixé par l'entreprise).
     * @return array{ok:bool, reason:string, remaining:?int, days:int}
     */
    public static function invoice(?int $userId, int $total): array
    {
        $pro = self::proAccount($userId);
        if ($pro === null) {
            return ['ok' => false, 'reason' => 'not_professional', 'remaining' => null, 'days' => 0];
        }
        if ((int) $pro['invoice_allowed'] !== 1) {
            return ['ok' => false, 'reason' => 'not_authorized', 'remaining' => null, 'days' => 0];
        }
        $remaining = $pro['credit_limit'] === null ? null : max(0, (int) $pro['credit_limit'] - self::outstanding((int) $userId));
        if ($remaining !== null && $total > $remaining) {
            return ['ok' => false, 'reason' => 'credit_limit', 'remaining' => $remaining, 'days' => (int) $pro['payment_days']];
        }
        return ['ok' => true, 'reason' => '', 'remaining' => $remaining, 'days' => (int) $pro['payment_days']];
    }

    /** Commande en gros : montant après remise au moins égal au seuil configuré (0 = notion désactivée). */
    public static function isWholesale(int $amountAfterDiscount): bool
    {
        $threshold = Settings::int('wholesale_threshold');
        return $threshold > 0 && $amountAfterDiscount >= $threshold;
    }

    /** Montant de l'acompte pour ce total, ou null si l'acompte n'est pas proposé. */
    public static function depositAmount(int $total): ?int
    {
        if (!Settings::bool('deposit_enabled') || $total <= 0 || $total < Settings::int('deposit_min_order')) {
            return null;
        }
        $value = Settings::int('deposit_value');
        $amount = Settings::get('deposit_mode') === 'fixed' ? $value : (int) ceil($total * $value / 100);
        return $amount > 0 && $amount < $total ? $amount : null;
    }

    /** L'acompte se règle avec un moyen encaissé avant livraison (prestataire ou paiement manuel). */
    public static function depositKind(string $kind): bool
    {
        return in_array($kind, ['gateway', 'manual'], true);
    }

    public static function terms(string $key): string
    {
        return Settings::localized($key);
    }
}
