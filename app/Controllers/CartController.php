<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CartService;
use App\Services\ShippingService;

final class CartController
{
    public function show(Request $r): Response
    {
        $summary = CartService::summary(CartService::current($r));
        return view('checkout/cart', [
            'summary' => $summary,
            'zones' => ShippingService::zones(),
            'pageTitle' => 'Panier',
            'noindex' => true,
        ]);
    }

    private function respond(Request $r, array $result, string $fallback = '/panier'): Response
    {
        if ($r->wantsJson()) {
            return Response::json(['ok' => $result['ok'], 'message' => $result['message'], 'count' => CartService::count($r)], $result['ok'] ? 200 : 422);
        }
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        return redirect(safe_return($r->str('retour'), $fallback));
    }

    public function add(Request $r): Response
    {
        $variantId = (int) $r->int('variant_id', 0);
        $raw = $r->post['qty'] ?? '1';
        $qty = is_string($raw) && preg_match('/^\d{1,4}$/', trim($raw)) ? (int) trim($raw) : 0;
        return $this->respond($r, CartService::add($r, $variantId, $qty));
    }

    public function update(Request $r): Response
    {
        $raw = $r->post['qty'] ?? '';
        if (!is_string($raw) || !preg_match('/^\d{1,4}$/', trim($raw))) {
            return $this->respond($r, ['ok' => false, 'message' => 'Quantité invalide.']);
        }
        return $this->respond($r, CartService::update($r, (int) $r->int('variant_id', 0), (int) trim($raw)));
    }

    public function remove(Request $r): Response
    {
        return $this->respond($r, CartService::remove($r, (int) $r->int('variant_id', 0)));
    }

    public function promo(Request $r): Response
    {
        return $this->respond($r, CartService::applyPromo($r, mb_substr($r->str('code'), 0, 40)));
    }

    public function removePromo(Request $r): Response
    {
        CartService::removePromo($r);
        return $this->respond($r, ['ok' => true, 'message' => 'Code promo retiré.']);
    }
}
