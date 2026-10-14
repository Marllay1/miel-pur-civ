<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Log;

/**
 * Création et cycle de vie des commandes. Le serveur recalcule tout : prix, remises, frais, total, stock.
 */
final class OrderService
{
    public const STATUSES = ['pending_confirmation', 'pending_payment', 'paid', 'preparing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    public const TRANSITIONS = [
        'pending_confirmation' => ['preparing', 'cancelled'],
        'pending_payment' => ['paid', 'cancelled'],
        'paid' => ['preparing', 'cancelled', 'refunded'],
        'preparing' => ['shipped', 'delivered', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => ['refunded'],
        'cancelled' => ['refunded'],
        'refunded' => [],
    ];

    /** Calcul des montants à partir d'un récapitulatif de panier serveur. */
    public static function quote(array $summary, ?array $zone): array
    {
        $after = max(0, (int) $summary['subtotal'] - (int) $summary['discount']);
        $shipping = $zone !== null ? ShippingService::fee($zone, $after, (bool) $summary['free_shipping']) : null;
        return [
            'subtotal' => (int) $summary['subtotal'],
            'discount' => (int) $summary['discount'],
            'shipping' => $shipping,
            'total' => $after + ($shipping ?? 0),
        ];
    }

    public static function newIdempotencyKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function findByNumber(string $number): ?array
    {
        return DB::one('SELECT * FROM orders WHERE number = ?', [$number]);
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public static function items(int $orderId): array
    {
        return DB::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public static function history(int $orderId): array
    {
        return DB::all('SELECT h.*, u.first_name, u.last_name FROM order_status_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.order_id = ? ORDER BY h.id', [$orderId]);
    }

    /** Accès : personnel autorisé, client propriétaire, ou porteur du lien secret. */
    public static function canAccess(array $order, ?string $token): bool
    {
        if (Auth::can('orders.manage') || Auth::can('customers.view')) {
            return true;
        }
        $uid = Auth::id();
        if ($uid !== null && $order['user_id'] !== null && (int) $order['user_id'] === $uid) {
            return true;
        }
        return is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token) === 1
            && hash_equals((string) $order['access_token_hash'], hash('sha256', $token));
    }

    /**
     * @param array $data champs validés : first_name, last_name, email, phone, zone_id, city, district, address, landmark, note, payment_method
     * @return array{status:string, order?:array, token?:string, errors?:array, total?:int}
     *   status ∈ created | existing | error | total_changed
     */
    public static function create(array $data, ?array $cart, ?int $userId, string $idempotencyKey, ?int $expectedTotal): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $idempotencyKey)) {
            return ['status' => 'error', 'errors' => [t('form.invalid_reload')]];
        }
        $existing = DB::one('SELECT * FROM orders WHERE idempotency_key = ?', [$idempotencyKey]);
        if ($existing !== null) {
            return ['status' => 'existing', 'order' => $existing];
        }
        if ($cart === null) {
            return ['status' => 'error', 'errors' => [t('cart.empty')]];
        }
        $token = bin2hex(random_bytes(16));
        try {
            $order = DB::transaction(static function () use ($data, $cart, $userId, $idempotencyKey, $expectedTotal, $token): array {
                $summary = CartService::summary($cart);
                if ($summary['items'] === []) {
                    throw new OrderException([t('cart.empty')]);
                }
                if ($summary['problems'] !== []) {
                    throw new OrderException($summary['problems']);
                }
                if ($summary['promo_error'] !== null) {
                    throw new OrderException([t('order.promo_error', ['error' => $summary['promo_error']])]);
                }
                $zone = ShippingService::find((int) $data['zone_id']);
                if ($zone === null) {
                    throw new OrderException([t('order.zone_invalid')]);
                }
                $isPickup = (int) $zone['is_pickup'] === 1;
                $method = (string) $data['payment_method'];
                $allowed = array_column(PaymentService::availableMethods($zone), 'code');
                if (!in_array($method, $allowed, true)) {
                    throw new OrderException([t('order.method_unavailable')]);
                }
                $min = Settings::int('min_order_amount');
                if ($min > 0 && $summary['subtotal'] < $min) {
                    throw new OrderException([t('order.min_amount', ['amount' => money($min)])]);
                }
                $quote = self::quote($summary, $zone);
                if ($expectedTotal === null || $expectedTotal !== $quote['total']) {
                    throw new OrderException([t('order.total_changed', ['total' => money($quote['total'])])], $quote['total']);
                }

                $now = gmdate('Y-m-d H:i:s');
                $online = $method !== 'cod';
                $number = 'MPCI-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $orderId = DB::insert('orders', [
                    'number' => $number,
                    'idempotency_key' => $idempotencyKey,
                    'access_token_hash' => hash('sha256', $token),
                    'user_id' => $userId,
                    'status' => $online ? 'pending_payment' : 'pending_confirmation',
                    'payment_method' => $method,
                    'payment_status' => $online ? 'pending' : 'unpaid',
                    'locale' => \App\Core\Lang::get(),
                    'customer_first_name' => $data['first_name'],
                    'customer_last_name' => $data['last_name'],
                    'customer_email' => mb_strtolower($data['email']),
                    'customer_phone' => $data['phone'],
                    'shipping_zone_id' => (int) $zone['id'],
                    'shipping_zone_name' => $zone['name'],
                    'is_pickup' => $isPickup ? 1 : 0,
                    'shipping_city' => $isPickup ? null : $data['city'],
                    'shipping_district' => $isPickup ? null : ($data['district'] ?: null),
                    'shipping_address' => $isPickup ? null : $data['address'],
                    'shipping_landmark' => $isPickup ? null : ($data['landmark'] ?: null),
                    'customer_note' => $data['note'] ?: null,
                    'subtotal' => $quote['subtotal'],
                    'discount' => $quote['discount'],
                    'shipping_fee' => (int) $quote['shipping'],
                    'total' => $quote['total'],
                    'promo_code' => $summary['promo']['code'] ?? null,
                    'promo_code_id' => isset($summary['promo']['id']) ? (int) $summary['promo']['id'] : null,
                    'expires_at' => $online ? gmdate('Y-m-d H:i:s', time() + 60 * max(5, Settings::int('payment_timeout_minutes'))) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($summary['items'] as $item) {
                    $qty = (int) $item['quantity'];
                    $updated = DB::run(
                        'UPDATE product_variants SET stock = stock - ?, updated_at = ? WHERE id = ? AND stock >= ? AND is_active = 1 AND deleted_at IS NULL',
                        [$qty, $now, (int) $item['variant_id'], $qty]
                    )->rowCount();
                    if ($updated !== 1) {
                        throw new OrderException([t('order.stock', ['item' => $item['product_name'] . ' – ' . $item['variant_label']])]);
                    }
                    DB::insert('order_items', [
                        'order_id' => $orderId,
                        'product_id' => (int) $item['product_id'],
                        'variant_id' => (int) $item['variant_id'],
                        'product_name' => $item['product_name'],
                        'variant_label' => $item['variant_label'],
                        'sku' => $item['sku'],
                        'unit_price' => (int) $item['price'],
                        'quantity' => $qty,
                        'line_total' => (int) $item['price'] * $qty,
                    ]);
                    DB::insert('stock_movements', [
                        'variant_id' => (int) $item['variant_id'], 'quantity' => -$qty, 'reason' => 'order',
                        'reference' => $number, 'user_id' => null, 'note' => null, 'created_at' => $now,
                    ]);
                }
                if (isset($summary['promo']['id']) && !PromoService::consume((int) $summary['promo']['id'])) {
                    throw new OrderException([t('order.promo_exhausted')]);
                }
                DB::insert('order_status_history', [
                    'order_id' => $orderId, 'from_status' => null,
                    'to_status' => $online ? 'pending_payment' : 'pending_confirmation',
                    'note' => 'Commande créée (' . payment_method_label($method) . ').', 'user_id' => null, 'created_at' => $now,
                ]);
                CartService::clear((int) $cart['id']);
                return DB::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
            });
        } catch (OrderException $e) {
            if ($e->newTotal !== null) {
                return ['status' => 'total_changed', 'errors' => $e->errors, 'total' => $e->newTotal];
            }
            return ['status' => 'error', 'errors' => $e->errors];
        } catch (\PDOException $e) {
            if (DB::isUniqueViolation($e)) {
                $existing = DB::one('SELECT * FROM orders WHERE idempotency_key = ?', [$idempotencyKey]);
                if ($existing !== null) {
                    return ['status' => 'existing', 'order' => $existing];
                }
            }
            throw $e;
        }

        try {
            Notifier::orderPlaced($order, $token);
        } catch (\Throwable $e) {
            Log::error('Notification de commande impossible : ' . $e->getMessage(), ['order' => $order['number']]);
        }
        return ['status' => 'created', 'order' => $order, 'token' => $token];
    }

    /** Transitions autorisées pour une commande donnée. */
    public static function allowedTransitions(array $order): array
    {
        $next = self::TRANSITIONS[$order['status']] ?? [];
        return array_values(array_filter($next, static function (string $to) use ($order): bool {
            if ($to === 'refunded') {
                return $order['payment_status'] === 'paid';
            }
            if ($order['status'] === 'preparing' && $to === 'delivered') {
                return (int) $order['is_pickup'] === 1;
            }
            return true;
        }));
    }

    /**
     * Change le statut (contrôle des transitions + verrou optimiste). Restitue stock et code promo à l'annulation.
     * @throws \DomainException
     */
    public static function transition(int $orderId, string $to, ?int $userId, string $note = ''): array
    {
        return DB::transaction(static function () use ($orderId, $to, $userId, $note): array {
            $order = self::find($orderId);
            if ($order === null) {
                throw new \DomainException('Commande introuvable.');
            }
            $from = $order['status'];
            if (!in_array($to, self::allowedTransitions($order), true)) {
                throw new \DomainException(sprintf('Transition impossible : « %s » → « %s ».', status_label($from), status_label($to)));
            }
            $now = gmdate('Y-m-d H:i:s');
            $changes = ['status' => $to, 'updated_at' => $now];
            $extraNote = '';
            if ($to === 'paid') {
                $changes['payment_status'] = 'paid';
                $changes['paid_at'] = $now;
                $changes['expires_at'] = null;
            } elseif ($to === 'delivered') {
                if ($order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid') {
                    $changes['payment_status'] = 'paid';
                    $changes['paid_at'] = $now;
                    $extraNote = ' Paiement à la livraison encaissé.';
                }
                DB::run('UPDATE shipments SET delivered_at = ?, updated_at = ? WHERE order_id = ?', [$now, $now, $orderId]);
            } elseif ($to === 'shipped') {
                self::upsertShipment($orderId, ['shipped_at' => $now]);
            } elseif ($to === 'cancelled') {
                $changes['cancelled_at'] = $now;
                $changes['expires_at'] = null;
                if ($order['payment_status'] === 'pending') {
                    $changes['payment_status'] = 'unpaid';
                }
            } elseif ($to === 'refunded') {
                $changes['payment_status'] = 'refunded';
            }
            $n = DB::update('orders', $changes, 'id = ? AND status = ?', [$orderId, $from]);
            if ($n !== 1) {
                throw new \DomainException('La commande a été modifiée entre-temps. Rechargez la page.');
            }
            if ($to === 'cancelled') {
                self::restoreStock($order, $userId);
                if ($order['promo_code_id'] !== null && (int) $order['promo_released'] === 0) {
                    PromoService::release((int) $order['promo_code_id']);
                    DB::update('orders', ['promo_released' => 1], 'id = ?', [$orderId]);
                }
                DB::run("UPDATE payments SET status = 'cancelled', updated_at = ? WHERE order_id = ? AND status = 'initiated'", [$now, $orderId]);
            }
            DB::insert('order_status_history', [
                'order_id' => $orderId, 'from_status' => $from, 'to_status' => $to,
                'note' => trim($note . $extraNote) ?: null, 'user_id' => $userId, 'created_at' => $now,
            ]);
            if ($userId !== null) {
                Audit::log('order.status', 'order', $order['number'], ['from' => $from, 'to' => $to, 'note' => $note], $userId);
            }
            $updated = self::find($orderId);
            try {
                Notifier::statusChanged($updated);
            } catch (\Throwable $e) {
                Log::error('Notification de statut impossible : ' . $e->getMessage());
            }
            return $updated;
        });
    }

    private static function restoreStock(array $order, ?int $userId): void
    {
        $claimed = DB::run('UPDATE orders SET stock_restored = 1 WHERE id = ? AND stock_restored = 0', [(int) $order['id']])->rowCount();
        if ($claimed !== 1) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        foreach (self::items((int) $order['id']) as $item) {
            if ($item['variant_id'] === null) {
                continue;
            }
            DB::run('UPDATE product_variants SET stock = stock + ?, updated_at = ? WHERE id = ?', [(int) $item['quantity'], $now, (int) $item['variant_id']]);
            DB::insert('stock_movements', [
                'variant_id' => (int) $item['variant_id'], 'quantity' => (int) $item['quantity'], 'reason' => 'cancel',
                'reference' => $order['number'], 'user_id' => $userId, 'note' => 'Annulation de commande', 'created_at' => $now,
            ]);
        }
    }

    public static function upsertShipment(int $orderId, array $fields): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $exists = DB::value('SELECT id FROM shipments WHERE order_id = ?', [$orderId]);
        if ($exists) {
            DB::update('shipments', $fields + ['updated_at' => $now], 'order_id = ?', [$orderId]);
        } else {
            DB::insert('shipments', $fields + ['order_id' => $orderId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public static function addNote(int $orderId, string $note, int $userId): void
    {
        $order = self::find($orderId);
        if ($order === null) {
            throw new \DomainException('Commande introuvable.');
        }
        DB::insert('order_status_history', [
            'order_id' => $orderId, 'from_status' => $order['status'], 'to_status' => $order['status'],
            'note' => 'Note interne : ' . $note, 'user_id' => $userId, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        Audit::log('order.note', 'order', $order['number'], [], $userId);
    }

    /** Annule les commandes en attente de paiement dont le délai est dépassé. */
    public static function expireOverdue(): int
    {
        $count = 0;
        $rows = DB::all("SELECT id, expires_at FROM orders WHERE status = 'pending_payment' AND expires_at IS NOT NULL AND expires_at < ?", [gmdate('Y-m-d H:i:s')]);
        foreach ($rows as $row) {
            $note = 'Délai de paiement dépassé : annulation automatique.';
            // Une notification de paiement peut s'être perdue : on interroge le prestataire avant d'annuler.
            $payment = PaymentService::latestForOrder((int) $row['id']);
            if ($payment !== null && $payment['status'] === 'initiated' && $payment['provider'] !== 'demo') {
                try {
                    $result = PaymentService::verify($payment);
                } catch (\Throwable $e) {
                    $result = 'unavailable';
                    Log::error('Vérification avant expiration impossible : ' . $e->getMessage(), ['ref' => $payment['reference']]);
                }
                if ($result === 'unavailable') {
                    // Prestataire injoignable : on réessaie aux passages suivants plutôt que d'annuler à tort,
                    // puis, après 24 h, on libère le stock en le signalant (un paiement tardif sera visible dans le journal).
                    if (strtotime((string) $row['expires_at'] . ' UTC') > time() - 86400) {
                        continue;
                    }
                    $note = 'Délai de paiement dépassé ; état du paiement invérifiable auprès du prestataire depuis 24 h : annulation automatique, à contrôler.';
                }
                if (DB::value('SELECT status FROM orders WHERE id = ?', [(int) $row['id']]) !== 'pending_payment') {
                    continue;
                }
            }
            try {
                self::transition((int) $row['id'], 'cancelled', null, $note);
                $count++;
            } catch (\DomainException $e) {
                Log::info('Expiration ignorée : ' . $e->getMessage());
            }
        }
        return $count;
    }
}
