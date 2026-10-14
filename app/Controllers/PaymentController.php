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
            throw new HttpException(404, 'Le paiement simulé est désactivé.');
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
        return view('checkout/demo-payment', [
            'payment' => $payment,
            'order' => $order,
            'token' => $token,
            'pageTitle' => 'Paiement SIMULÉ',
            'noindex' => true,
            'bodyClass' => 'page-demo-payment',
        ]);
    }

    /** Simule la réponse du prestataire : passe par le même traitement signé que le webhook réel. */
    public function demoSubmit(Request $r): Response
    {
        [$payment, $order, $token, $gateway] = $this->demoContext($r);
        $outcome = $r->str('outcome');
        if (!in_array($outcome, ['succeeded', 'failed', 'cancelled'], true)) {
            throw new HttpException(422, 'Choix invalide.');
        }
        $notification = $gateway->buildNotification($payment, $outcome);
        $result = PaymentService::handleWebhook('demo', $notification['headers'], $notification['body']);
        $messages = [
            'paid' => ['success', 'Paiement SIMULÉ accepté : la commande est marquée comme payée.'],
            'failed' => ['error', 'Paiement SIMULÉ refusé. Vous pouvez réessayer.'],
            'cancelled' => ['info', 'Paiement SIMULÉ annulé. Vous pouvez réessayer.'],
            'already_processed' => ['info', 'Ce paiement a déjà été traité.'],
        ];
        [$type, $message] = $messages[$result['result']] ?? ['error', 'Le paiement n\'a pas pu être traité (' . $result['result'] . ').'];
        Session::flash($type, $message);
        return redirect(url('/commande/' . $order['number'], ['t' => $token]));
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
        $result = PaymentService::handleWebhook($provider, $r->headers, $r->body);
        if ($result['http'] !== 200) {
            Log::info('Webhook refusé', ['provider' => $provider, 'ip' => $r->ip(), 'reason' => $result['result']]);
        }
        return Response::json($result['http'] === 200 ? ['received' => true, 'result' => $result['result']] : ['error' => $result['result']], $result['http']);
    }
}
