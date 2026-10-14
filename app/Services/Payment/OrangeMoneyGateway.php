<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\HttpClient;

/**
 * Orange Money Web Payment (developer.orange.com/apis/om-webpay).
 *  - POST /oauth/v3/token (Basic consumer_key:consumer_secret, grant_type=client_credentials) → access_token
 *  - POST {chemin}/webpayment { merchant_key, currency, order_id, amount, return_url, cancel_url, notif_url, lang, reference }
 *      → pay_token, payment_url, notif_token
 *  - POST {chemin}/transactionstatus { order_id, amount, pay_token } → status INITIATED|PENDING|SUCCESS|FAILED|EXPIRED
 *  - Notification POST { status, notif_token, txnid } : le notif_token est comparé à celui reçu à l'initiation,
 *    puis l'état est confirmé par transactionstatus.
 * Chemin et devise à confirmer avec Orange Côte d'Ivoire : ORANGE_MONEY_PATH (bac à sable : /orange-money-webpay/dev/v1),
 * ORANGE_MONEY_CURRENCY (bac à sable : OUV ; production : XOF).
 */
final class OrangeMoneyGateway extends AbstractGateway
{
    public function code(): string
    {
        return 'orange_money';
    }

    public function requiredEnv(): array
    {
        return ['ORANGE_MONEY_CLIENT_ID', 'ORANGE_MONEY_CLIENT_SECRET', 'ORANGE_MONEY_MERCHANT_KEY', 'ORANGE_MONEY_PATH'];
    }

    private function base(): string
    {
        return rtrim($this->env('ORANGE_MONEY_API_BASE', 'https://api.orange.com'), '/');
    }

    private function path(): string
    {
        return '/' . trim($this->env('ORANGE_MONEY_PATH'), '/');
    }

    private function token(): string
    {
        return $this->cachedToken('oauth', function (): array {
            $res = HttpClient::request('POST', $this->base() . '/oauth/v3/token', [
                'Authorization' => 'Basic ' . base64_encode($this->env('ORANGE_MONEY_CLIENT_ID') . ':' . $this->env('ORANGE_MONEY_CLIENT_SECRET')),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ], 'grant_type=client_credentials');
            $token = $res['json']['access_token'] ?? null;
            if ($res['status'] !== 200 || !is_string($token)) {
                $this->fail('jeton d\'accès', $res);
            }
            return [$token, (int) ($res['json']['expires_in'] ?? 3600)];
        });
    }

    public function start(array $payment, array $order, string $orderToken): array
    {
        $res = HttpClient::json('POST', $this->base() . $this->path() . '/webpayment', [
            'merchant_key' => $this->env('ORANGE_MONEY_MERCHANT_KEY'),
            'currency' => $this->env('ORANGE_MONEY_CURRENCY', 'XOF'),
            'order_id' => $payment['reference'],
            'amount' => (int) $payment['amount'],
            'return_url' => $this->returnUrl($payment, $orderToken),
            'cancel_url' => $this->returnUrl($payment, $orderToken, 'annule'),
            'notif_url' => $this->notifyUrl(),
            'lang' => locale(),
            'reference' => mb_substr(\App\Services\Settings::get('store_name'), 0, 30),
        ], ['Authorization' => 'Bearer ' . $this->token()]);
        $url = $res['json']['payment_url'] ?? null;
        $pay = $res['json']['pay_token'] ?? null;
        $notif = $res['json']['notif_token'] ?? null;
        if (!in_array($res['status'], [200, 201], true) || !is_string($url) || !is_string($pay) || !str_starts_with($url, 'https://')) {
            $this->fail('initiation du paiement', $res);
        }
        return ['redirect' => $url, 'pending' => false, 'provider_token' => $pay . '|' . (is_string($notif) ? $notif : '')];
    }

    public function fetchStatus(array $payment): ?array
    {
        [$pay] = explode('|', (string) ($payment['provider_token'] ?? '') . '|');
        if ($pay === '') {
            return null;
        }
        $res = HttpClient::json('POST', $this->base() . $this->path() . '/transactionstatus', [
            'order_id' => $payment['reference'], 'amount' => (int) $payment['amount'], 'pay_token' => $pay,
        ], ['Authorization' => 'Bearer ' . $this->token()]);
        if (!in_array($res['status'], [200, 201], true) || !is_array($res['json'])) {
            return null;
        }
        $j = $res['json'];
        $status = match ($j['status'] ?? '') {
            'SUCCESS' => 'succeeded',
            'FAILED' => 'failed',
            'EXPIRED' => 'cancelled',
            default => 'pending',
        };
        // L'état est demandé pour le montant enregistré côté serveur : Orange répond pour cette transaction précise.
        return ['status' => $status, 'amount' => (int) $payment['amount'], 'provider_reference' => $j['txnid'] ?? null];
    }

    public function parseNotification(array $headers, string $body, array $post): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = $post;
        }
        $notif = is_string($data['notif_token'] ?? null) ? $data['notif_token'] : null;
        if ($notif === null || !preg_match('/^[A-Za-z0-9]{8,64}$/', $notif)) {
            throw new WebhookException('Contenu invalide.', 422);
        }
        return ['reference' => null, 'token' => $notif, 'event' => null];
    }
}
