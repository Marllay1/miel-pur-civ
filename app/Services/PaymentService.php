<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Services\Payment\DemoGateway;
use App\Services\Payment\Gateway;
use App\Services\Payment\UnconfiguredGateway;
use App\Services\Payment\WebhookException;

final class PaymentService
{
    /** @return array<string, Gateway> */
    public static function gateways(): array
    {
        return [
            'demo' => new DemoGateway(),
            'orange_money' => new UnconfiguredGateway('orange_money', 'Orange Money', ['ORANGE_MONEY_CLIENT_ID', 'ORANGE_MONEY_CLIENT_SECRET', 'ORANGE_MONEY_MERCHANT_KEY', 'ORANGE_MONEY_WEBHOOK_SECRET'], 'API Orange Money Web Payment — contrat marchand Orange Côte d\'Ivoire requis.'),
            'mtn_momo' => new UnconfiguredGateway('mtn_momo', 'MTN Mobile Money', ['MTN_MOMO_SUBSCRIPTION_KEY', 'MTN_MOMO_API_USER', 'MTN_MOMO_API_KEY', 'MTN_MOMO_WEBHOOK_SECRET'], 'API MTN MoMo (Collections) — compte marchand MTN Côte d\'Ivoire requis.'),
            'wave' => new UnconfiguredGateway('wave', 'Wave', ['WAVE_API_KEY', 'WAVE_WEBHOOK_SECRET'], 'API Wave Checkout — compte Wave Business requis.'),
            'card' => new UnconfiguredGateway('card', 'Carte bancaire', ['CARD_PROVIDER', 'CARD_API_KEY', 'CARD_WEBHOOK_SECRET'], 'Agrégateur de paiement par carte (CinetPay, PayDunya, etc.) à choisir. Aucun numéro de carte ne doit transiter par ce serveur.'),
        ];
    }

    public static function gateway(string $code): ?Gateway
    {
        return self::gateways()[$code] ?? null;
    }

    /**
     * Moyens de paiement réellement proposables pour une zone.
     * @return array<int, array{code:string, label:string, description:string, online:bool}>
     */
    public static function availableMethods(?array $zone): array
    {
        $methods = [];
        if (Settings::bool('cod_enabled') && ($zone === null || (int) $zone['allows_cod'] === 1)) {
            $methods[] = [
                'code' => 'cod',
                'label' => 'Paiement à la livraison',
                'description' => $zone !== null && (int) $zone['is_pickup'] === 1
                    ? 'Vous payez au moment du retrait.'
                    : 'Vous payez en espèces à la réception. Notre équipe vous appelle pour confirmer la commande.',
                'online' => false,
            ];
        }
        foreach (self::gateways() as $gateway) {
            if ($gateway->isEnabled()) {
                $methods[] = ['code' => $gateway->code(), 'label' => $gateway->label(), 'description' => $gateway->description(), 'online' => true];
            }
        }
        return $methods;
    }

    public static function findByReference(string $reference): ?array
    {
        return DB::one('SELECT * FROM payments WHERE reference = ?', [$reference]);
    }

    /** Crée (ou réutilise) une tentative de paiement pour une commande en attente. */
    public static function initiate(array $order, string $provider): array
    {
        $gateway = self::gateway($provider);
        if ($gateway === null || !$gateway->isEnabled()) {
            throw new \DomainException('Ce moyen de paiement n\'est pas disponible.');
        }
        if ($order['status'] !== 'pending_payment') {
            throw new \DomainException('Cette commande n\'est pas en attente de paiement.');
        }
        if ($order['expires_at'] !== null && $order['expires_at'] < gmdate('Y-m-d H:i:s')) {
            throw new \DomainException('Le délai de paiement de cette commande est dépassé.');
        }
        $existing = DB::one("SELECT * FROM payments WHERE order_id = ? AND provider = ? AND status = 'initiated' ORDER BY id DESC LIMIT 1", [(int) $order['id'], $provider]);
        if ($existing !== null && (int) $existing['amount'] === (int) $order['total']) {
            return $existing;
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::insert('payments', [
            'order_id' => (int) $order['id'],
            'provider' => $provider,
            'reference' => 'PAY-' . strtoupper(bin2hex(random_bytes(6))),
            'amount' => (int) $order['total'],
            'currency' => 'XOF',
            'status' => 'initiated',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return DB::one('SELECT * FROM payments WHERE id = ?', [$id]);
    }

    /**
     * Point d'entrée des notifications serveur à serveur.
     * @return array{http:int, result:string}
     */
    public static function handleWebhook(string $provider, array $headers, string $body): array
    {
        $gateway = self::gateway($provider);
        if ($gateway === null) {
            return ['http' => 404, 'result' => 'unknown_provider'];
        }
        try {
            $event = $gateway->parseWebhook($headers, $body);
        } catch (WebhookException $e) {
            return ['http' => $e->status, 'result' => $e->getMessage()];
        }
        $result = self::applyResult($provider, $event, $body);
        return ['http' => 200, 'result' => $result];
    }

    /**
     * Applique un résultat de paiement de façon idempotente.
     * Résultats : paid, failed, cancelled, duplicate, already_processed, unknown_payment, amount_mismatch, paid_closed_order.
     */
    public static function applyResult(string $provider, array $event, string $rawPayload): string
    {
        return DB::transaction(static function () use ($provider, $event, $rawPayload): string {
            $now = gmdate('Y-m-d H:i:s');
            $payment = DB::one('SELECT * FROM payments WHERE reference = ? AND provider = ?', [$event['reference'], $provider]);
            try {
                $eventRowId = DB::insert('payment_events', [
                    'provider' => $provider,
                    'event_id' => $event['event_id'],
                    'payment_id' => $payment['id'] ?? null,
                    'type' => $event['status'],
                    'result' => null,
                    'payload' => mb_substr($rawPayload, 0, 10000),
                    'created_at' => $now,
                ]);
            } catch (\PDOException $e) {
                if (DB::isUniqueViolation($e)) {
                    return 'duplicate';
                }
                throw $e;
            }
            $finish = static function (string $result) use ($eventRowId): string {
                DB::update('payment_events', ['result' => $result], 'id = ?', [$eventRowId]);
                return $result;
            };
            if ($payment === null) {
                return $finish('unknown_payment');
            }
            if ($payment['status'] !== 'initiated') {
                return $finish('already_processed');
            }
            $order = OrderService::find((int) $payment['order_id']);
            if ((int) $event['amount'] !== (int) $payment['amount']) {
                Audit::log('payment.amount_mismatch', 'payment', $payment['reference'], ['expected' => (int) $payment['amount'], 'received' => (int) $event['amount']], null);
                return $finish('amount_mismatch');
            }
            if ($event['status'] === 'succeeded') {
                DB::update('payments', ['status' => 'succeeded', 'provider_reference' => $event['provider_reference'], 'updated_at' => $now], 'id = ?', [(int) $payment['id']]);
                if ($order !== null && $order['status'] === 'pending_payment') {
                    OrderService::transition((int) $order['id'], 'paid', null, 'Paiement confirmé (' . payment_method_label($provider) . ', réf. ' . $payment['reference'] . ').');
                    return $finish('paid');
                }
                Audit::log('payment.received_on_closed_order', 'order', $order['number'] ?? null, [
                    'payment' => $payment['reference'], 'order_status' => $order['status'] ?? null, 'action' => 'Traitement manuel requis (remboursement ou réactivation).',
                ], null);
                if ($order !== null) {
                    DB::update('orders', ['admin_note' => trim(($order['admin_note'] ?? '') . "\n[" . $now . '] Paiement ' . $payment['reference'] . ' reçu alors que la commande était « ' . status_label($order['status']) . ' » : traitement manuel requis.'), 'updated_at' => $now], 'id = ?', [(int) $order['id']]);
                }
                return $finish('paid_closed_order');
            }
            $status = $event['status'] === 'failed' ? 'failed' : 'cancelled';
            DB::update('payments', ['status' => $status, 'failure_reason' => $status === 'failed' ? 'Refusé par le prestataire' : 'Annulé par le client', 'updated_at' => $now], 'id = ?', [(int) $payment['id']]);
            if ($order !== null && $order['status'] === 'pending_payment') {
                DB::update('orders', ['payment_status' => $status === 'failed' ? 'failed' : 'pending', 'updated_at' => $now], 'id = ?', [(int) $order['id']]);
                DB::insert('order_status_history', [
                    'order_id' => (int) $order['id'], 'from_status' => 'pending_payment', 'to_status' => 'pending_payment',
                    'note' => $status === 'failed' ? 'Tentative de paiement échouée.' : 'Paiement annulé par le client.', 'user_id' => null, 'created_at' => $now,
                ]);
            }
            return $finish($status);
        });
    }
}
