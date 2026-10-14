<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Log;
use App\Services\Payment\CinetPayGateway;
use App\Services\Payment\DemoGateway;
use App\Services\Payment\Gateway;
use App\Services\Payment\MtnMomoGateway;
use App\Services\Payment\OrangeMoneyGateway;
use App\Services\Payment\PaymentException;
use App\Services\Payment\WaveGateway;
use App\Services\Payment\WebhookException;

final class PaymentService
{
    /** @return array<string, Gateway> */
    public static function gateways(): array
    {
        return [
            'wave' => new WaveGateway(),
            'orange_money' => new OrangeMoneyGateway(),
            'mtn_momo' => new MtnMomoGateway(),
            'card' => new CinetPayGateway(),
            'demo' => new DemoGateway(),
        ];
    }

    public static function gateway(string $code): ?Gateway
    {
        return self::gateways()[$code] ?? null;
    }

    /**
     * Moyens de paiement réellement proposables pour une zone et, si connus, un montant, un client et le caractère « en gros » de la commande.
     * Paiement sur facture : seulement pour un professionnel autorisé par un administrateur, dans la limite de son plafond.
     * @return array<int, array{code:string, kind:string, label:string, description:string, online:bool, needs_phone:bool, logos:array, deposit:bool}>
     */
    public static function availableMethods(?array $zone, ?int $total = null, ?int $userId = null, bool $wholesale = false): array
    {
        // Source unique : Administration → Moyens de paiement (seuls les moyens opérationnels sont proposés).
        $methods = [];
        foreach (PaymentMethods::operational() as $code => $m) {
            if ($total !== null && !PaymentMethods::allowsAmount($m, $total)) {
                continue;
            }
            $custom = PaymentMethods::description($m);
            if ($m['kind'] === 'invoice') {
                if ($total === null || !PaymentPolicy::invoice($userId, $total)['ok']) {
                    continue;
                }
                $methods[] = ['code' => $code, 'kind' => 'invoice', 'label' => PaymentMethods::name($m), 'description' => $custom !== '' ? $custom : t('pay.desc.invoice'),
                    'online' => false, 'needs_phone' => false, 'logos' => PaymentMethods::logos($m), 'deposit' => false];
                continue;
            }
            if ($m['kind'] === 'cod') {
                if ($zone !== null && (int) $zone['allows_cod'] !== 1) {
                    continue;
                }
                if ($wholesale && !Settings::bool('wholesale_cod_allowed')) {
                    continue;
                }
                $description = $custom !== '' ? $custom : ($zone !== null && (int) $zone['is_pickup'] === 1 ? t('pay.desc.cod_pickup') : t('pay.desc.cod'));
                $methods[] = ['code' => $code, 'kind' => 'cod', 'label' => PaymentMethods::name($m), 'description' => $description, 'online' => false, 'needs_phone' => false,
                    'logos' => PaymentMethods::logos($m), 'deposit' => false];
            } elseif ($m['kind'] === 'gateway') {
                $gateway = self::gateway($code);
                $methods[] = ['code' => $code, 'kind' => 'gateway', 'label' => PaymentMethods::name($m), 'description' => $custom !== '' ? $custom : $gateway->description(),
                    'online' => true, 'needs_phone' => $gateway->needsPhone(), 'logos' => PaymentMethods::logos($m), 'deposit' => true];
            } else {
                $methods[] = ['code' => $code, 'kind' => 'manual', 'label' => PaymentMethods::name($m), 'description' => $custom !== '' ? $custom : t('pay.desc.manual'),
                    'online' => false, 'needs_phone' => false, 'logos' => PaymentMethods::logos($m), 'deposit' => true];
            }
        }
        $demo = self::gateway('demo');
        if ($demo !== null && $demo->isEnabled()) {
            $methods[] = ['code' => 'demo', 'kind' => 'gateway', 'label' => $demo->label(), 'description' => $demo->description(), 'online' => true, 'needs_phone' => false, 'logos' => [], 'deposit' => true];
        }
        return $methods;
    }

    /** Paiement en ligne possible : en attente de paiement, ou solde restant d'une commande avec acompte non annulée. */
    public static function canPayOnline(array $order): bool
    {
        $gateway = self::gateway((string) $order['payment_method']);
        if ($gateway === null || !$gateway->isEnabled() || PaymentLedger::dueNow($order) <= 0) {
            return false;
        }
        if ($order['status'] === 'pending_payment') {
            return $order['expires_at'] === null || $order['expires_at'] > gmdate('Y-m-d H:i:s');
        }
        return $order['payment_terms'] === 'deposit' && in_array($order['status'], ['deposit_paid', 'preparing', 'shipped', 'delivered'], true);
    }

    public static function findByReference(string $reference): ?array
    {
        return DB::one('SELECT * FROM payments WHERE reference = ?', [$reference]);
    }

    public static function latestForOrder(int $orderId): ?array
    {
        return DB::one('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$orderId]);
    }

    /**
     * Crée une tentative de paiement et la démarre chez le prestataire.
     * @return array{payment:array, redirect:?string, pending:bool}
     * @throws \DomainException|PaymentException
     */
    public static function initiate(array $order, string $orderToken, ?string $payerPhone = null): array
    {
        $provider = $order['payment_method'];
        $gateway = self::gateway($provider);
        if ($gateway === null || !$gateway->isEnabled()) {
            throw new \DomainException(t('pay.unavailable'));
        }
        // En attente de paiement (paiement intégral ou acompte), ou solde restant après acompte.
        $due = PaymentLedger::dueNow($order);
        if (!self::canPayOnline($order) || $due <= 0) {
            throw new \DomainException(t('pay.not_pending'));
        }
        if ($order['status'] === 'pending_payment' && $order['expires_at'] !== null && $order['expires_at'] < gmdate('Y-m-d H:i:s')) {
            throw new \DomainException(t('pay.expired'));
        }
        if ($gateway->needsPhone() && ($payerPhone === null || MtnMomoGateway::msisdn($payerPhone) === null)) {
            throw new PaymentException(t('pay.phone_invalid'));
        }
        // Une tentative encore ouverte (redirection non utilisée) est réutilisée, sauf paiement poussé sur mobile.
        $existing = DB::one("SELECT * FROM payments WHERE order_id = ? AND provider = ? AND status = 'initiated' ORDER BY id DESC LIMIT 1", [(int) $order['id'], $provider]);
        if ($existing !== null && (int) $existing['amount'] === $due && $existing['checkout_url'] && !$gateway->needsPhone()) {
            return ['payment' => $existing, 'redirect' => $existing['checkout_url'], 'pending' => false];
        }
        if ($existing !== null) {
            DB::update('payments', ['status' => 'cancelled', 'failure_reason' => 'Remplacée par une nouvelle tentative', 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $existing['id']]);
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::insert('payments', [
            'order_id' => (int) $order['id'],
            'provider' => $provider,
            'reference' => 'PAY-' . strtoupper(bin2hex(random_bytes(6))),
            'amount' => $due,
            'currency' => 'XOF',
            'status' => 'initiated',
            'payer_phone' => $payerPhone !== null ? mb_substr($payerPhone, 0, 30) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $payment = DB::one('SELECT * FROM payments WHERE id = ?', [$id]);
        try {
            $started = $gateway->start($payment, $order, $orderToken);
        } catch (PaymentException $e) {
            DB::update('payments', ['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 255), 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$id]);
            throw $e;
        }
        DB::update('payments', ['provider_token' => $started['provider_token'], 'checkout_url' => $started['redirect'] !== null ? mb_substr($started['redirect'], 0, 500) : null, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$id]);
        return ['payment' => DB::one('SELECT * FROM payments WHERE id = ?', [$id]), 'redirect' => $started['redirect'], 'pending' => $started['pending']];
    }

    /**
     * Interroge le prestataire et applique le résultat s'il est définitif.
     * @return string résultat (paid, failed, cancelled, pending, already_processed, unavailable…)
     */
    public static function verify(array $payment): string
    {
        if ($payment['status'] !== 'initiated') {
            return 'already_processed';
        }
        $gateway = self::gateway($payment['provider']);
        if ($gateway === null) {
            return 'unavailable';
        }
        DB::update('payments', ['last_checked_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $payment['id']]);
        $status = $gateway->fetchStatus($payment);
        if ($status === null) {
            return 'unavailable';
        }
        if ($status['status'] === 'pending') {
            return 'pending';
        }
        return self::applyResult($payment['provider'], [
            'event_id' => 'verify:' . $payment['reference'] . ':' . $status['status'] . ':' . (int) ($status['amount'] ?? $payment['amount']),
            'reference' => $payment['reference'],
            'status' => $status['status'],
            'amount' => $status['amount'] ?? (int) $payment['amount'],
            'provider_reference' => $status['provider_reference'],
        ], json_encode(['source' => 'verification', 'status' => $status], JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * Notification serveur à serveur. Les résultats signés sont appliqués directement ;
     * les autres servent à identifier le paiement, dont l'état est alors demandé au prestataire.
     * @return array{http:int, result:string}
     */
    public static function handleWebhook(string $provider, array $headers, string $body, array $post = []): array
    {
        $gateway = self::gateway($provider);
        if ($gateway === null || ($provider !== 'demo' && !$gateway->isEnabled())) {
            return ['http' => 404, 'result' => 'unknown_provider'];
        }
        try {
            $note = $gateway->parseNotification($headers, $body, $post);
        } catch (WebhookException $e) {
            return ['http' => $e->status, 'result' => $e->getMessage()];
        }
        if ($note['event'] !== null) {
            return ['http' => 200, 'result' => self::applyResult($provider, $note['event'], $body)];
        }
        $payment = null;
        if ($note['reference'] !== null) {
            $payment = DB::one('SELECT * FROM payments WHERE reference = ? AND provider = ?', [$note['reference'], $provider]);
        } elseif ($note['token'] !== null) {
            foreach (DB::all("SELECT * FROM payments WHERE provider = ? AND status = 'initiated' AND provider_token IS NOT NULL ORDER BY id DESC LIMIT 200", [$provider]) as $p) {
                $parts = explode('|', (string) $p['provider_token']);
                if (hash_equals((string) end($parts), $note['token']) || hash_equals($parts[0], $note['token'])) {
                    $payment = $p;
                    break;
                }
            }
        }
        if ($payment === null) {
            return ['http' => 200, 'result' => 'ignored'];
        }
        return ['http' => 200, 'result' => self::verify($payment)];
    }

    /** Vérifie les paiements en ligne restés en attente (tâche planifiée), avant toute annulation pour expiration. */
    public static function verifyPending(int $limit = 50): int
    {
        $done = 0;
        foreach (DB::all("SELECT * FROM payments WHERE status = 'initiated' AND provider <> 'demo' ORDER BY id LIMIT " . (int) $limit) as $p) {
            try {
                if (!in_array(self::verify($p), ['pending', 'unavailable'], true)) {
                    $done++;
                }
            } catch (\Throwable $e) {
                Log::error('Vérification de paiement impossible : ' . $e->getMessage(), ['ref' => $p['reference']]);
            }
        }
        return $done;
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
                $open = $order !== null && !in_array($order['status'], ['cancelled', 'refunded'], true);
                if ($order !== null) {
                    // Encaissement vérifié chez le prestataire : inscrit au journal (y compris sur une commande close, pour pouvoir le rembourser).
                    PaymentLedger::record((int) $order['id'], (int) $payment['amount'], $provider, $event['provider_reference'] ?: $payment['reference'], null,
                        'Paiement confirmé (' . payment_method_label($provider) . ', réf. ' . $payment['reference'] . ').', (int) $payment['id'], true);
                }
                if ($open) {
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
