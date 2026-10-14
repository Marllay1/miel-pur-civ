<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Cookie;
use App\Core\DB;
use App\Core\Request;

/**
 * Panier persistant : cookie opaque mpci_cart (48 hex, 30 jours) ou compte client.
 * Les prix et stocks sont toujours relus en base ; le navigateur n'envoie que des identifiants et quantités.
 */
final class CartService
{
    public const COOKIE = 'mpci_cart';
    public const TTL = 2592000;

    public static function token(Request $r): ?string
    {
        $t = Cookie::$queued[self::COOKIE]['value'] ?? $r->cookie(self::COOKIE);
        return is_string($t) && preg_match('/^[a-f0-9]{48}$/', $t) ? $t : null;
    }

    public static function current(Request $r, bool $create = false): ?array
    {
        $now = gmdate('Y-m-d H:i:s');
        $userId = Auth::id();
        if ($userId !== null) {
            $cart = DB::one('SELECT * FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
            if ($cart !== null || !$create) {
                return $cart;
            }
            $id = DB::insert('carts', ['token' => null, 'user_id' => $userId, 'promo_code' => null, 'created_at' => $now, 'updated_at' => $now]);
            return DB::one('SELECT * FROM carts WHERE id = ?', [$id]);
        }
        $token = self::token($r);
        if ($token !== null) {
            $cart = DB::one('SELECT * FROM carts WHERE token = ? AND user_id IS NULL', [$token]);
            if ($cart !== null) {
                return $cart;
            }
        }
        if (!$create) {
            return null;
        }
        $token = bin2hex(random_bytes(24));
        $id = DB::insert('carts', ['token' => $token, 'user_id' => null, 'promo_code' => null, 'created_at' => $now, 'updated_at' => $now]);
        Cookie::queue(self::COOKIE, $token, self::TTL);
        return DB::one('SELECT * FROM carts WHERE id = ?', [$id]);
    }

    public static function items(int $cartId): array
    {
        $rows = DB::all(
            'SELECT ci.id AS item_id, ci.variant_id, ci.quantity, v.label AS variant_label, v.sku, v.price, v.stock, v.format,
                    v.is_active AS v_active, v.deleted_at AS v_deleted, p.id AS product_id, p.name AS product_name, p.slug AS product_slug,
                    p.is_active AS p_active, p.deleted_at AS p_deleted, c.is_active AS c_active,
                    (SELECT i.path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image_path
             FROM cart_items ci
             JOIN product_variants v ON v.id = ci.variant_id
             JOIN products p ON p.id = v.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE ci.cart_id = ?
             ORDER BY p.name, v.sort_order, v.id',
            [$cartId]
        );
        $rows = I18n::localize('product', $rows, 'product_id', ['name' => 'product_name']);
        $rows = I18n::localize('variant', $rows, 'variant_id', ['label' => 'variant_label']);
        foreach ($rows as &$row) {
            $row['available'] = (int) $row['v_active'] === 1 && $row['v_deleted'] === null && (int) $row['p_active'] === 1
                && $row['p_deleted'] === null && ($row['c_active'] === null || (int) $row['c_active'] === 1);
            $row['enough_stock'] = (int) $row['stock'] >= (int) $row['quantity'];
            $row['line_total'] = (int) $row['price'] * (int) $row['quantity'];
        }
        return $rows;
    }

    /**
     * Récapitulatif recalculé côté serveur.
     * @return array{cart:?array, items:array, count:int, subtotal:int, promo:?array, promo_error:?string, discount:int, free_shipping:bool, problems:string[]}
     */
    public static function summary(?array $cart): array
    {
        $out = ['cart' => $cart, 'items' => [], 'count' => 0, 'subtotal' => 0, 'promo' => null, 'promo_error' => null, 'discount' => 0, 'free_shipping' => false, 'problems' => []];
        if ($cart === null) {
            return $out;
        }
        $out['items'] = self::items((int) $cart['id']);
        foreach ($out['items'] as $item) {
            $out['count'] += (int) $item['quantity'];
            if (!$item['available']) {
                $out['problems'][] = t('cart.problem_unavailable', ['item' => $item['product_name'] . ' – ' . $item['variant_label']]);
                continue;
            }
            if (!$item['enough_stock']) {
                $out['problems'][] = t('cart.problem_stock', ['item' => $item['product_name'] . ' – ' . $item['variant_label'], 'count' => (int) $item['stock']]);
            }
            $out['subtotal'] += $item['line_total'];
        }
        if ($cart['promo_code']) {
            $promo = PromoService::find($cart['promo_code']);
            $eval = PromoService::evaluate($promo, $out['subtotal']);
            if ($eval['valid']) {
                $out['promo'] = $promo;
                $out['discount'] = $eval['discount'];
                $out['free_shipping'] = $eval['free_shipping'];
            } else {
                $out['promo_error'] = $eval['error'];
            }
        }
        return $out;
    }

    public static function count(Request $r): int
    {
        $cart = self::current($r);
        return $cart ? (int) DB::value('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?', [(int) $cart['id']]) : 0;
    }

    private static function maxQty(): int
    {
        return max(1, Settings::int('max_qty_per_line'));
    }

    /** @return array{ok:bool, message:string} */
    public static function add(Request $r, int $variantId, int $qty): array
    {
        if ($qty < 1 || $qty > self::maxQty()) {
            return ['ok' => false, 'message' => t('cart.qty_invalid', ['max' => self::maxQty()])];
        }
        $variant = Catalog::purchasableVariant($variantId);
        if ($variant === null) {
            return ['ok' => false, 'message' => t('cart.unavailable')];
        }
        $cart = self::current($r, true);
        $existing = (int) DB::value('SELECT quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?', [(int) $cart['id'], $variantId]);
        $wanted = $existing + $qty;
        if ($wanted > (int) $variant['stock']) {
            $left = max(0, (int) $variant['stock'] - $existing);
            return ['ok' => false, 'message' => (int) $variant['stock'] === 0
                ? t('cart.out_of_stock')
                : t('cart.stock_left', ['count' => $left])];
        }
        if ($wanted > self::maxQty()) {
            return ['ok' => false, 'message' => t('cart.max_line_pro', ['max' => self::maxQty()])];
        }
        if ($existing > 0) {
            DB::run('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND variant_id = ?', [$wanted, (int) $cart['id'], $variantId]);
        } else {
            DB::insert('cart_items', ['cart_id' => (int) $cart['id'], 'variant_id' => $variantId, 'quantity' => $qty, 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
        self::touch((int) $cart['id']);
        return ['ok' => true, 'message' => t('cart.added', ['item' => $variant['product_name'] . ' – ' . $variant['label']])];
    }

    /** @return array{ok:bool, message:string} */
    public static function update(Request $r, int $variantId, int $qty): array
    {
        $cart = self::current($r);
        if ($cart === null) {
            return ['ok' => false, 'message' => t('cart.not_found')];
        }
        if ($qty <= 0) {
            return self::remove($r, $variantId);
        }
        if ($qty > self::maxQty()) {
            return ['ok' => false, 'message' => t('cart.max_line', ['max' => self::maxQty()])];
        }
        $stock = DB::value('SELECT stock FROM product_variants WHERE id = ?', [$variantId]);
        if ($stock === null) {
            return ['ok' => false, 'message' => t('cart.item_not_found')];
        }
        if ($qty > (int) $stock) {
            return ['ok' => false, 'message' => t('cart.stock_available', ['count' => (int) $stock])];
        }
        $n = DB::run('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND variant_id = ?', [$qty, (int) $cart['id'], $variantId])->rowCount();
        self::touch((int) $cart['id']);
        return $n > 0 ? ['ok' => true, 'message' => t('cart.updated')] : ['ok' => false, 'message' => t('cart.not_in_cart')];
    }

    /** @return array{ok:bool, message:string} */
    public static function remove(Request $r, int $variantId): array
    {
        $cart = self::current($r);
        if ($cart !== null) {
            DB::run('DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?', [(int) $cart['id'], $variantId]);
            self::touch((int) $cart['id']);
        }
        return ['ok' => true, 'message' => t('cart.removed')];
    }

    /** @return array{ok:bool, message:string} */
    public static function applyPromo(Request $r, string $code): array
    {
        $cart = self::current($r);
        if ($cart === null) {
            return ['ok' => false, 'message' => t('cart.empty')];
        }
        $promo = PromoService::find($code);
        $summary = self::summary($cart);
        $eval = PromoService::evaluate($promo, $summary['subtotal']);
        if (!$eval['valid']) {
            return ['ok' => false, 'message' => (string) $eval['error']];
        }
        DB::update('carts', ['promo_code' => $promo['code'], 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $cart['id']]);
        return ['ok' => true, 'message' => t('promo.applied', ['code' => $promo['code']])];
    }

    public static function removePromo(Request $r): void
    {
        $cart = self::current($r);
        if ($cart !== null) {
            DB::update('carts', ['promo_code' => null, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $cart['id']]);
        }
    }

    public static function clear(int $cartId): void
    {
        DB::run('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
        DB::update('carts', ['promo_code' => null, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$cartId]);
    }

    /** Fusionne le panier invité (cookie) dans le panier du compte, à la connexion. */
    public static function mergeGuestCart(Request $r, int $userId): void
    {
        $token = self::token($r);
        if ($token === null) {
            return;
        }
        $guest = DB::one('SELECT * FROM carts WHERE token = ? AND user_id IS NULL', [$token]);
        if ($guest === null) {
            return;
        }
        $mine = DB::one('SELECT * FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        if ($mine === null) {
            DB::update('carts', ['user_id' => $userId, 'token' => null, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $guest['id']]);
        } else {
            foreach (DB::all('SELECT ci.variant_id, ci.quantity, v.stock FROM cart_items ci JOIN product_variants v ON v.id = ci.variant_id WHERE ci.cart_id = ?', [(int) $guest['id']]) as $item) {
                $existing = (int) DB::value('SELECT quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?', [(int) $mine['id'], (int) $item['variant_id']]);
                $qty = min((int) $item['stock'], self::maxQty(), $existing + (int) $item['quantity']);
                if ($qty <= 0) {
                    continue;
                }
                if ($existing > 0) {
                    DB::run('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND variant_id = ?', [$qty, (int) $mine['id'], (int) $item['variant_id']]);
                } else {
                    DB::insert('cart_items', ['cart_id' => (int) $mine['id'], 'variant_id' => (int) $item['variant_id'], 'quantity' => $qty, 'created_at' => gmdate('Y-m-d H:i:s')]);
                }
            }
            if ($guest['promo_code'] && !$mine['promo_code']) {
                DB::update('carts', ['promo_code' => $guest['promo_code']], 'id = ?', [(int) $mine['id']]);
            }
            DB::run('DELETE FROM carts WHERE id = ?', [(int) $guest['id']]);
        }
        Cookie::forget(self::COOKIE);
    }

    private static function touch(int $cartId): void
    {
        DB::update('carts', ['updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$cartId]);
    }
}
