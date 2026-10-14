<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\PaymentException;
use App\Services\PaymentService;
use App\Services\ShippingService;

final class CheckoutController
{
    public function form(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        $summary = CartService::summary(CartService::current($r));
        if ($summary['items'] === []) {
            Session::flash('error', t('cart.empty'));
            return redirect('/panier');
        }
        if ($summary['problems'] !== [] || $summary['promo_error'] !== null) {
            foreach ($summary['problems'] as $p) {
                Session::flash('error', $p);
            }
            if ($summary['promo_error'] !== null) {
                Session::flash('error', t('order.promo_error', ['error' => $summary['promo_error']]));
            }
            return redirect('/panier');
        }
        $key = Session::get('checkout_key');
        if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/', $key)) {
            $key = OrderService::newIdempotencyKey();
            Session::set('checkout_key', $key);
        }
        // Une zone sans moyen de paiement utilisable (ex. expédition payable en ligne alors qu'aucun prestataire n'est configuré)
        // n'est pas proposée : le client ne pourrait pas terminer sa commande.
        $zones = array_values(array_filter(ShippingService::zones(), static fn (array $z) => PaymentService::availableMethods($z) !== []));
        $user = Auth::user();
        $addresses = $user ? DB::all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id', [(int) $user['id']]) : [];
        if ($old === [] && $user) {
            $default = $addresses[0] ?? null;
            $old = [
                'first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'email' => $user['email'], 'phone' => $user['phone'] ?? '',
                'city' => $default['city'] ?? '', 'district' => $default['district'] ?? '', 'address' => $default['address_line'] ?? '', 'landmark' => $default['landmark'] ?? '',
            ];
        }
        $zone = null;
        foreach ($zones as $z) {
            if ((int) ($old['zone_id'] ?? 0) === (int) $z['id']) {
                $zone = $z;
            }
        }
        $methodsByZone = [];
        $methods = [];
        foreach ($zones as $z) {
            foreach (PaymentService::availableMethods($z) as $m) {
                $methodsByZone[(int) $z['id']][] = $m['code'];
                $methods[$m['code']] = $m;
            }
        }
        $quote = OrderService::quote($summary, $zone);
        return view('checkout/form', [
            'summary' => $summary,
            'zones' => $zones,
            'zone' => $zone,
            'quote' => $quote,
            'methods' => $methods,
            'methodsByZone' => $methodsByZone,
            'addresses' => $addresses,
            'errors' => $errors,
            'old' => $old,
            'idempotencyKey' => $key,
            'expectedTotal' => $old['expected_total'] ?? ($zone !== null ? $quote['total'] : ''),
            'pageTitle' => t('checkout.title'),
            'noindex' => true,
        ], $status);
    }

    public function estimate(Request $r): Response
    {
        $summary = CartService::summary(CartService::current($r));
        $zone = ShippingService::find((int) $r->int('zone_id', 0));
        $quote = OrderService::quote($summary, $zone);
        return Response::json([
            'ok' => $zone !== null && $summary['items'] !== [],
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'shipping' => $quote['shipping'],
            'total' => $quote['total'],
            'formatted' => [
                'subtotal' => money($quote['subtotal']),
                'discount' => '−' . money($quote['discount']),
                'shipping' => $quote['shipping'] === null ? t('checkout.choose_zone') : ($quote['shipping'] === 0 ? t('checkout.free') : money($quote['shipping'])),
                'total' => money($quote['total']),
            ],
            'is_pickup' => $zone !== null && (int) $zone['is_pickup'] === 1,
            'methods' => array_column(PaymentService::availableMethods($zone), 'code'),
        ]);
    }

    public function store(Request $r): Response
    {
        $cart = CartService::current($r);
        $key = $r->str('idempotency_key');
        // Double soumission : la commande existe déjà avec cette clé.
        if (preg_match('/^[a-f0-9]{32}$/', $key) && ($existing = DB::one('SELECT * FROM orders WHERE idempotency_key = ?', [$key])) !== null) {
            return $this->redirectToOrder($existing, t('checkout.already_saved'));
        }
        $zone = ShippingService::find((int) $r->int('zone_id', 0));
        $rules = [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'zone_id' => 'required|int',
            'payment_method' => 'required|max:30',
            'note' => 'max:1000',
            'cgv' => 'accepted',
        ];
        if ($zone === null || (int) $zone['is_pickup'] === 0) {
            $rules += ['city' => 'required|max:100', 'district' => 'max:150', 'address' => 'required|max:255', 'landmark' => 'max:255'];
        }
        $gateway = PaymentService::gateway($r->str('payment_method'));
        if ($gateway !== null && $gateway->needsPhone()) {
            $rules['payer_phone'] = 'required|phone';
        }
        $labels = ['first_name' => t('field.first_name'), 'last_name' => t('field.last_name'), 'email' => t('field.email_label'), 'phone' => t('field.phone'),
            'zone_id' => t('field.zone'), 'payment_method' => t('field.payment_method'), 'city' => t('field.city'), 'district' => t('field.district'),
            'address' => t('field.address'), 'landmark' => t('field.landmark'), 'note' => t('field.note'), 'payer_phone' => t('field.payer_phone')];
        $v = Validator::make($r->post, $rules, $labels);
        $errors = $v->errors;
        if ($zone === null && !isset($errors['zone_id'])) {
            $errors['zone_id'] = t('checkout.choose_zone_error');
        }
        if (isset($rules['payer_phone']) && !isset($errors['payer_phone']) && \App\Services\Payment\MtnMomoGateway::msisdn($r->str('payer_phone')) === null) {
            $errors['payer_phone'] = t('pay.phone_invalid');
        }
        if ($key === '' || $key !== Session::get('checkout_key')) {
            $errors['_'] = t('checkout.form_expired');
        }
        if ($errors) {
            return $this->form($r, $errors, $r->post, 422);
        }
        $data = $v->data + ['city' => '', 'district' => '', 'address' => '', 'landmark' => ''];
        $result = OrderService::create($data, $cart, Auth::id(), $key, $r->int('expected_total'));
        switch ($result['status']) {
            case 'created':
                $order = $result['order'];
                Session::set('checkout_key', OrderService::newIdempotencyKey());
                $tokens = (array) Session::get('order_tokens', []);
                $tokens[$order['number']] = $result['token'];
                Session::set('order_tokens', array_slice($tokens, -10, null, true));
                if ($order['status'] === 'pending_payment') {
                    // Paiement en ligne : démarrage immédiat chez le prestataire.
                    return $this->startPayment($order, $result['token'], $data['payer_phone'] ?? null);
                }
                Session::flash('success', t('checkout.thanks', ['number' => $order['number']]));
                return redirect('/commande/' . $order['number'] . '?t=' . $result['token']);
            case 'existing':
                return $this->redirectToOrder($result['order'], t('checkout.already_saved'));
            case 'total_changed':
                return $this->form($r, ['_' => $result['errors'][0]], ['expected_total' => $result['total']] + $r->post, 409);
            default:
                return $this->form($r, array_combine(array_map(static fn ($i) => 'e' . $i, array_keys($result['errors'])), $result['errors']), $r->post, 422);
        }
    }

    private function startPayment(array $order, string $token, ?string $phone): Response
    {
        try {
            $started = PaymentService::initiate($order, $token, $phone);
        } catch (\DomainException | PaymentException $e) {
            Session::flash('error', $e->getMessage());
            return redirect(url('/commande/' . $order['number'], ['t' => $token]));
        }
        if ($started['pending']) {
            return redirect(url('/commande/' . $order['number'] . '/paiement', ['t' => $token]));
        }
        return Response::redirectExternal((string) $started['redirect']);
    }

    private function redirectToOrder(array $order, string $message): Response
    {
        Session::flash('info', $message);
        $token = ((array) Session::get('order_tokens', []))[$order['number']] ?? null;
        if (is_string($token)) {
            return redirect('/commande/' . $order['number'] . '?t=' . $token);
        }
        if (Auth::id() !== null && (int) $order['user_id'] === Auth::id()) {
            return redirect('/commande/' . $order['number']);
        }
        return redirect('/suivi-commande');
    }

    /** @return array{0: array, 1: string} commande + jeton (éventuellement vide) */
    public static function authorize(Request $r): array
    {
        $order = OrderService::findByNumber($r->params['number']);
        $token = (string) ($r->post['t'] ?? $r->query['t'] ?? '');
        if ($order === null || !OrderService::canAccess($order, $token)) {
            throw new HttpException(404, t('order.not_found'));
        }
        return [$order, preg_match('/^[a-f0-9]{32}$/', $token) ? $token : ''];
    }

    public function show(Request $r): Response
    {
        [$order, $token] = self::authorize($r);
        $gateway = PaymentService::gateway($order['payment_method']);
        $canPay = $order['status'] === 'pending_payment' && $gateway !== null && $gateway->isEnabled()
            && ($order['expires_at'] === null || $order['expires_at'] > gmdate('Y-m-d H:i:s'));
        $history = array_values(array_filter(OrderService::history((int) $order['id']), static fn ($h) => !str_starts_with((string) $h['note'], 'Note interne')));
        return view('checkout/order', [
            'order' => $order,
            'token' => $token,
            'items' => OrderService::items((int) $order['id']),
            'history' => $history,
            'shipment' => DB::one('SELECT * FROM shipments WHERE order_id = ?', [(int) $order['id']]),
            'canPay' => $canPay,
            'needsPhone' => $gateway !== null && $gateway->needsPhone(),
            'lastPayment' => PaymentService::latestForOrder((int) $order['id']),
            'pageTitle' => t('order.title', ['number' => $order['number']]),
            'noindex' => true,
        ]);
    }

    public function pay(Request $r): Response
    {
        [$order, $token] = self::authorize($r);
        if (!RateLimiter::hit('pay:' . $order['number'], 10, 600)) {
            Session::flash('error', t('pay.throttled'));
            return redirect(url('/commande/' . $order['number'], ['t' => $token]));
        }
        $phone = $r->str('payer_phone');
        return $this->startPayment($order, $token, $phone !== '' ? $phone : null);
    }

    /** Page d'attente (paiement poussé sur le téléphone, MTN MoMo). */
    public function waiting(Request $r): Response
    {
        [$order, $token] = self::authorize($r);
        $payment = PaymentService::latestForOrder((int) $order['id']);
        if ($payment === null || $order['status'] !== 'pending_payment') {
            return redirect(url('/commande/' . $order['number'], ['t' => $token]));
        }
        return view('checkout/payment-wait', ['order' => $order, 'payment' => $payment, 'token' => $token, 'pageTitle' => t('pay.wait_title'), 'noindex' => true]);
    }

    /** État du paiement (JSON) : interroge le prestataire au plus toutes les 4 secondes. */
    public function paymentStatus(Request $r): Response
    {
        [$order, $token] = self::authorize($r);
        $payment = PaymentService::latestForOrder((int) $order['id']);
        $result = 'none';
        if ($payment !== null && $payment['status'] === 'initiated') {
            $recent = $payment['last_checked_at'] !== null && strtotime($payment['last_checked_at']) > time() - 4;
            $result = $recent ? 'pending' : PaymentService::verify($payment);
            $payment = PaymentService::findByReference($payment['reference']);
        }
        $order = OrderService::find((int) $order['id']);
        return Response::json([
            'order_status' => $order['status'],
            'payment_status' => $payment['status'] ?? null,
            'result' => $result,
            'done' => $order['status'] !== 'pending_payment' || in_array($payment['status'] ?? '', ['failed', 'cancelled'], true),
            'redirect' => \App\Core\Lang::path(url('/commande/' . $order['number'], ['t' => $token])),
        ]);
    }

    public function receipt(Request $r): Response
    {
        [$order, $token] = self::authorize($r);
        return view('checkout/receipt', [
            'order' => $order,
            'token' => $token,
            'items' => OrderService::items((int) $order['id']),
            'pageTitle' => t('receipt.title', ['number' => $order['number']]),
            'noindex' => true,
        ], 200, 'layouts/print');
    }
}
