<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\DB;

/**
 * Encaissements et remboursements d'une commande (table order_transactions) : montant encaissé, restant dû,
 * acompte, remboursements, état du paiement. Seuls les encaissements confirmés (prestataire vérifié par le serveur,
 * ou réception constatée par l'équipe) sont enregistrés ; une déclaration du client n'en est pas un.
 */
final class PaymentLedger
{
    public const PAYMENT_STATUSES = ['pending', 'verifying', 'deposit_paid', 'partial', 'paid', 'cod', 'deferred', 'failed', 'cancelled', 'refunded', 'partially_refunded'];

    /** Moyens d'encaissement proposés à l'équipe pour un enregistrement manuel. */
    public static function methods(): array
    {
        $out = [];
        foreach (PaymentMethods::all() as $code => $m) {
            $out[$code] = PaymentMethods::name($m);
        }
        return $out + ['cash' => 'Espèces', 'transfer' => 'Virement bancaire', 'cheque' => 'Chèque', 'other' => 'Autre'];
    }

    public static function balance(array $o): int
    {
        return max(0, (int) $o['total'] - (int) $o['amount_paid']);
    }

    /** Montant à payer maintenant : l'acompte tant qu'il n'est pas réglé, sinon le solde. */
    public static function dueNow(array $o): int
    {
        if ($o['payment_terms'] === 'deposit' && $o['deposit_amount'] !== null && (int) $o['amount_paid'] < (int) $o['deposit_amount']) {
            return (int) $o['deposit_amount'] - (int) $o['amount_paid'];
        }
        return self::balance($o);
    }

    public static function pendingRefunds(int $orderId): int
    {
        return (int) DB::value("SELECT COALESCE(SUM(amount), 0) FROM order_transactions WHERE order_id = ? AND type = 'refund' AND status = 'pending'", [$orderId]);
    }

    /** Montant encore remboursable (encaissé − remboursé − remboursements en attente). */
    public static function refundable(array $o): int
    {
        return max(0, (int) $o['amount_paid'] - (int) $o['amount_refunded'] - self::pendingRefunds((int) $o['id']));
    }

    public static function transactions(int $orderId): array
    {
        return DB::all('SELECT t.*, u.first_name, u.last_name FROM order_transactions t LEFT JOIN users u ON u.id = t.user_id WHERE t.order_id = ? ORDER BY t.id', [$orderId]);
    }

    /** État du paiement déduit des montants (aucune valeur fournie par le navigateur). */
    public static function computeStatus(array $o): string
    {
        $paid = (int) $o['amount_paid'];
        $refunded = (int) $o['amount_refunded'];
        if ($refunded > 0) {
            return $refunded >= $paid ? 'refunded' : 'partially_refunded';
        }
        if ($paid > 0 && $paid >= (int) $o['total']) {
            return 'paid';
        }
        if ($paid > 0) {
            return $o['payment_terms'] === 'deposit' && $o['deposit_amount'] !== null && $paid >= (int) $o['deposit_amount'] ? 'deposit_paid' : 'partial';
        }
        if ($o['status'] === 'cancelled') {
            return 'cancelled';
        }
        return match ($o['payment_terms']) {
            'cod' => 'cod',
            'invoice' => 'deferred',
            default => $o['payment_declared_at'] !== null ? 'verifying' : ($o['payment_status'] === 'failed' ? 'failed' : 'pending'),
        };
    }

    public static function refreshStatus(int $orderId): array
    {
        $o = OrderService::find($orderId);
        $status = self::computeStatus($o);
        if ($status !== $o['payment_status']) {
            DB::update('orders', ['payment_status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$orderId]);
            $o['payment_status'] = $status;
        }
        return $o;
    }

    /**
     * Enregistre un encaissement confirmé, met à jour les montants et fait avancer la commande
     * (en attente de paiement → acompte reçu / payée ; acompte reçu → payée).
     * $force : paiement vérifié chez un prestataire, enregistré même sur une commande close ou au-delà du solde
     * (l'argent a bien été reçu : il doit apparaître pour être remboursé).
     * @throws \DomainException
     */
    public static function record(int $orderId, int $amount, string $method, ?string $reference, ?int $userId, string $note = '', ?int $paymentId = null, bool $force = false): array
    {
        return DB::transaction(static function () use ($orderId, $amount, $method, $reference, $userId, $note, $paymentId, $force): array {
            $o = OrderService::find($orderId);
            if ($o === null) {
                throw new \DomainException('Commande introuvable.');
            }
            if ($amount <= 0) {
                throw new \DomainException('Le montant doit être supérieur à 0.');
            }
            if (!$force && in_array($o['status'], ['cancelled', 'refunded'], true)) {
                throw new \DomainException('Commande annulée ou remboursée : aucun encaissement ne peut y être ajouté.');
            }
            if (!$force && $amount > self::balance($o)) {
                throw new \DomainException('Le montant dépasse le reste à payer (' . money(self::balance($o)) . ').');
            }
            $now = gmdate('Y-m-d H:i:s');
            DB::insert('order_transactions', [
                'order_id' => $orderId, 'type' => 'payment', 'method' => mb_substr($method, 0, 30), 'amount' => $amount, 'status' => 'completed',
                'reference' => $reference !== null && $reference !== '' ? mb_substr($reference, 0, 120) : null, 'reason' => $note !== '' ? mb_substr($note, 0, 255) : null,
                'payment_id' => $paymentId, 'user_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::run('UPDATE orders SET amount_paid = amount_paid + ?, payment_declared_at = NULL, updated_at = ? WHERE id = ?', [$amount, $now, $orderId]);
            $o = self::refreshStatus($orderId);
            DB::insert('order_status_history', [
                'order_id' => $orderId, 'from_status' => $o['status'], 'to_status' => $o['status'],
                'note' => 'Encaissement de ' . money($amount) . ' (' . (self::methods()[$method] ?? $method) . ($reference ? ', réf. ' . $reference : '') . ').',
                'user_id' => $userId, 'created_at' => $now,
            ]);
            Audit::log('payment.record', 'order', $o['number'], ['amount' => $amount, 'method' => $method, 'reference' => $reference, 'paid' => (int) $o['amount_paid']], $userId);

            $next = null;
            if ((int) $o['amount_paid'] >= (int) $o['total'] && in_array($o['status'], ['pending_payment', 'deposit_paid'], true)) {
                $next = 'paid';
            } elseif ($o['status'] === 'pending_payment' && $o['payment_status'] === 'deposit_paid') {
                $next = 'deposit_paid';
            }
            if ($next !== null) {
                $o = OrderService::transition($orderId, $next, $userId, $note, ['from_ledger' => true]);
            }
            return $o;
        });
    }

    /**
     * Enregistre un remboursement (effectué ou à effectuer), avec son motif. Aucun acompte n'est conservé automatiquement :
     * l'équipe décide selon les conditions de vente.
     * @throws \DomainException
     */
    public static function refund(int $orderId, int $amount, string $reason, string $method, ?string $reference, ?int $userId, bool $completed = true): array
    {
        return DB::transaction(static function () use ($orderId, $amount, $reason, $method, $reference, $userId, $completed): array {
            $o = OrderService::find($orderId);
            if ($o === null) {
                throw new \DomainException('Commande introuvable.');
            }
            if (trim($reason) === '') {
                throw new \DomainException('Le motif du remboursement est obligatoire.');
            }
            if ($amount <= 0 || $amount > self::refundable($o)) {
                throw new \DomainException('Montant de remboursement invalide (remboursable : ' . money(self::refundable($o)) . ').');
            }
            $now = gmdate('Y-m-d H:i:s');
            DB::insert('order_transactions', [
                'order_id' => $orderId, 'type' => 'refund', 'method' => mb_substr($method, 0, 30), 'amount' => $amount, 'status' => $completed ? 'completed' : 'pending',
                'reference' => $reference !== null && $reference !== '' ? mb_substr($reference, 0, 120) : null, 'reason' => mb_substr($reason, 0, 255),
                'payment_id' => null, 'user_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            Audit::log('payment.refund', 'order', $o['number'], ['amount' => $amount, 'reason' => $reason, 'completed' => $completed], $userId);
            DB::insert('order_status_history', [
                'order_id' => $orderId, 'from_status' => $o['status'], 'to_status' => $o['status'],
                'note' => ($completed ? 'Remboursement effectué : ' : 'Remboursement à effectuer : ') . money($amount) . ' — ' . $reason . '.', 'user_id' => $userId, 'created_at' => $now,
            ]);
            return $completed ? self::applyRefund($orderId, $amount, $userId) : OrderService::find($orderId);
        });
    }

    /** Marque un remboursement en attente comme effectué. */
    public static function completeRefund(int $transactionId, ?string $reference, ?int $userId): array
    {
        return DB::transaction(static function () use ($transactionId, $reference, $userId): array {
            $tx = DB::one("SELECT * FROM order_transactions WHERE id = ? AND type = 'refund' AND status = 'pending'", [$transactionId]);
            if ($tx === null) {
                throw new \DomainException('Remboursement introuvable ou déjà traité.');
            }
            DB::update('order_transactions', ['status' => 'completed', 'reference' => $reference !== null && $reference !== '' ? mb_substr($reference, 0, 120) : $tx['reference'], 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$transactionId]);
            $o = OrderService::find((int) $tx['order_id']);
            Audit::log('payment.refund_completed', 'order', $o['number'], ['amount' => (int) $tx['amount']], $userId);
            return self::applyRefund((int) $tx['order_id'], (int) $tx['amount'], $userId);
        });
    }

    private static function applyRefund(int $orderId, int $amount, ?int $userId): array
    {
        DB::run('UPDATE orders SET amount_refunded = amount_refunded + ?, updated_at = ? WHERE id = ?', [$amount, gmdate('Y-m-d H:i:s'), $orderId]);
        $o = self::refreshStatus($orderId);
        if ($o['payment_status'] === 'refunded' && in_array('refunded', OrderService::TRANSITIONS[$o['status']] ?? [], true)) {
            $o = OrderService::transition($orderId, 'refunded', $userId, 'Remboursement intégral effectué.', ['from_ledger' => true]);
        }
        return $o;
    }
}
