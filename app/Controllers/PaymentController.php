<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Log;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\OrderService;
use App\Services\Payment\DemoGateway;
use App\Services\PaymentService;

final class PaymentController
{
    /** @return array{0: array, 1: array, 2: string, 3: DemoGateway} */
    private function demoContext(Request $r): array
    {
        $gateway = PaymentService::gateway('demo');
        if (!$gateway instanceof DemoGateway || !$gateway->isEnabled()) {
            throw new HttpException(404);
        }
        $payment = PaymentService::findByReference($r->params['reference']);
        if ($payment === null || $payment['provider'] !== 'demo') {
            throw new HttpException(404);
        }
        $order = OrderService::find((int) $payment['order_id']);
        $token = (string) ($r->post['t'] ?? $r->query['t'] ?? '');
        if ($order === null || !OrderService::canAccess($order, $token)) {
            throw new HttpException(404);
        }
        return [$payment, $order, preg_match('/^[a-f0-9]{32}$/', $token) ? $token : '', $gateway];
    }

    public function demo(Request $r): Response
    {
        [$payment, $order, $token] = $this->demoContext($r);
        return view('checkout/demo-payment', ['payment' => $payment, 'order' => $order, 'token' => $token, 'pageTitle' => t('pay.demo'), 'noindex' => true]);
    }

    /** Simule la réponse du prestataire : passe par le même traitement signé qu'une notification réelle. */
    public function demoSubmit(Request $r): Response
    {
        [$payment, $order, $token, $gateway] = $this->demoContext($r);
        $outcome = $r->str('outcome');
        if (!in_array($outcome, ['succeeded', 'failed', 'cancelled'], true)) {
            throw new HttpException(422);
        }
        $n = $gateway->buildNotification($payment, $outcome);
        $result = PaymentService::handleWebhook('demo', $n['headers'], $n['body']);
        $this->flashResult($result['result']);
        return redirect(url('/commande/' . $order['number'], ['t' => $token]));
    }

    private function flashResult(string $result): void
    {
        [$type, $key] = match ($result) {
            'paid', 'already_processed' => ['success', 'pay.result.paid'],
            'failed' => ['error', 'pay.result.failed'],
            'cancelled' => ['info', 'pay.result.cancelled'],
            'pending', 'unavailable' => ['info', 'pay.result.pending'],
            default => ['error', 'pay.result.error'],
        };
        Session::flash($type, t($key));
    }

    /** Retour du client depuis la page du prestataire : l'état est vérifié auprès du prestataire, jamais déduit de l'URL. */
    public function returnFrom(Request $r): Response
    {
        $payment = PaymentService::findByReference($r->str('ref'));
        if ($payment === null || $payment['provider'] !== $r->params['provider']) {
            throw new HttpException(404);
        }
        $order = OrderService::find((int) $payment['order_id']);
        $token = $r->str('t');
        if ($order === null || !OrderService::canAccess($order, $token)) {
            throw new HttpException(404);
        }
        $result = $payment['status'] === 'initiated' ? PaymentService::verify($payment) : ($payment['status'] === 'succeeded' ? 'paid' : $payment['status']);
        if ($result === 'pending' && in_array($r->str('issue'), ['echec', 'annule'], true)) {
            $result = $r->str('issue') === 'echec' ? 'failed' : 'cancelled';
            Session::flash($result === 'failed' ? 'error' : 'info', t('pay.result.' . $result));
        } else {
            $this->flashResult($result);
        }
        return redirect(url('/commande/' . $order['number'], ['t' => preg_match('/^[a-f0-9]{32}$/', $token) ? $token : null]));
    }

    public function webhook(Request $r): Response
    {
        if (!RateLimiter::hit('webhook:' . $r->ip(), 120, 60)) {
            return Response::json(['error' => 'Trop de requêtes'], 429);
        }
        if (strlen($r->body) > 65536) {
            return Response::json(['error' => 'Contenu trop volumineux'], 413);
        }
        $provider = $r->params['provider'];
        $result = PaymentService::handleWebhook($provider, $r->headers, $r->body, $r->post);
        if ($result['http'] !== 200) {
            Log::info('Notification de paiement refusée', ['provider' => $provider, 'ip' => $r->ip(), 'reason' => $result['result']]);
        }
        return Response::json($result['http'] === 200 ? ['received' => true, 'result' => $result['result']] : ['error' => $result['result']], $result['http']);
    }
}
