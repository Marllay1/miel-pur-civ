<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ShippingService;

final class CheckoutController
{
    public function form(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        $summary = CartService::summary(CartService::current($r));
        if ($summary['items'] === []) {
            Session::flash('error', 'Votre panier est vide.');
            return redirect('/panier');
        }
        if ($summary['problems'] !== [] || $summary['promo_error'] !== null) {
            foreach ($summary['problems'] as $p) {
                Session::flash('error', $p);
            }
            if ($summary['promo_error'] !== null) {
                Session::flash('error', 'Code promo : ' . $summary['promo_error']);
            }
            return redirect('/panier');
        }
        $key = Session::get('checkout_key');
        if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/', $key)) {
            $key = OrderService::newIdempotencyKey();
            Session::set('checkout_key', $key);
        }
        $zones = ShippingService::zones();
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
            'pageTitle' => 'Commande',
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
                'shipping' => $quote['shipping'] === null ? 'Choisissez une zone' : ($quote['shipping'] === 0 ? 'Offerte' : money($quote['shipping'])),
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
            return $this->redirectToOrder($existing, 'Cette commande a déjà été enregistrée.');
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
        $labels = ['first_name' => 'Le prénom', 'last_name' => 'Le nom', 'email' => 'L\'e-mail', 'phone' => 'Le téléphone', 'zone_id' => 'La zone de livraison',
            'payment_method' => 'Le moyen de paiement', 'city' => 'La ville', 'district' => 'La commune / le quartier', 'address' => 'L\'adresse', 'landmark' => 'Le point de repère', 'note' => 'La note'];
        $v = Validator::make($r->post, $rules, $labels);
        $errors = $v->errors;
        if ($zone === null && !isset($errors['zone_id'])) {
            $errors['zone_id'] = 'Choisissez une zone de livraison.';
        }
        if ($key === '' || $key !== Session::get('checkout_key')) {
            $errors['_'] = 'Le formulaire a expiré. Vérifiez vos informations puis confirmez à nouveau.';
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
                Session::flash('success', 'Merci ! Votre commande ' . $order['number'] . ' est enregistrée. Un e-mail de confirmation vous a été envoyé.');
                return redirect('/commande/' . $order['number'] . '?t=' . $result['token']);
            case 'existing':
                return $this->redirectToOrder($result['order'], 'Cette commande a déjà été enregistrée.');
            case 'total_changed':
                return $this->form($r, ['_' => $result['errors'][0]], ['expected_total' => $result['total']] + $r->post, 409);
            default:
                return $this->form($r, array_combine(array_map(static fn ($i) => 'e' . $i, array_keys($result['errors'])), $result['errors']), $r->post, 422);
        }
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
    private function authorize(Request $r): array
    {
        $order = OrderService::findByNumber($r->params['number']);
        $token = (string) ($r->post['t'] ?? $r->query['t'] ?? '');
        if ($order === null || !OrderService::canAccess($order, $token)) {
            throw new HttpException(404, 'Commande introuvable. Utilisez le lien reçu par e-mail ou la page de suivi.');
        }
        return [$order, preg_match('/^[a-f0-9]{32}$/', $token) ? $token : ''];
    }

    public function show(Request $r): Response
    {
        [$order, $token] = $this->authorize($r);
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
            'pageTitle' => 'Commande ' . $order['number'],
            'noindex' => true,
        ]);
    }

    public function pay(Request $r): Response
    {
        [$order, $token] = $this->authorize($r);
        try {
            $payment = PaymentService::initiate($order, $order['payment_method']);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            return redirect(url('/commande/' . $order['number'], ['t' => $token]));
        }
        return redirect(PaymentService::gateway($order['payment_method'])->checkoutUrl($payment, $token));
    }

    public function receipt(Request $r): Response
    {
        [$order, $token] = $this->authorize($r);
        return view('checkout/receipt', [
            'order' => $order,
            'token' => $token,
            'items' => OrderService::items((int) $order['id']),
            'pageTitle' => 'Récapitulatif ' . $order['number'],
            'noindex' => true,
        ], 200, 'layouts/print');
    }
}
