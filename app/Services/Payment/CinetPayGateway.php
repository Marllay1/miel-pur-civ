<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\HttpClient;
use App\Core\Log;

/**
 * Carte bancaire via CinetPay (docs.cinetpay.com) — page de paiement hébergée : aucun numéro de carte ne transite ici.
 *  - POST /v2/payment { apikey, site_id, transaction_id, amount, currency, description, notify_url, return_url, channels, customer_* }
 *      → data.payment_url
 *  - POST /v2/payment/check { apikey, site_id, transaction_id } → data.status ACCEPTED|REFUSED|… , data.amount
 *  - Notification POST (formulaire) cpm_site_id, cpm_trans_id… + en-tête x-token (HMAC-SHA256) : la documentation
 *    recommande de toujours confirmer par /v2/payment/check, ce qui est fait systématiquement.
 */
final class CinetPayGateway extends AbstractGateway
{
    public const TOKEN_FIELDS = ['cpm_site_id', 'cpm_trans_id', 'cpm_trans_date', 'cpm_amount', 'cpm_currency', 'signature', 'payment_method',
        'cel_phone_num', 'cpm_phone_prefixe', 'cpm_language', 'cpm_version', 'cpm_payment_config', 'cpm_page_action', 'cpm_custom', 'cpm_designation', 'cpm_error_message'];

    public function code(): string
    {
        return 'card';
    }

    public function requiredEnv(): array
    {
        return ['CINETPAY_API_KEY', 'CINETPAY_SITE_ID'];
    }

    private function base(): string
    {
        return rtrim($this->env('CINETPAY_API_BASE', 'https://api-checkout.cinetpay.com'), '/');
    }

    public function start(array $payment, array $order, string $orderToken): array
    {
        $amount = (int) $payment['amount'];
        if ($amount < 100 || $amount % 5 !== 0) {
            throw new PaymentException(t('pay.card_amount'));
        }
        $res = HttpClient::json('POST', $this->base() . '/v2/payment', [
            'apikey' => $this->env('CINETPAY_API_KEY'),
            'site_id' => $this->env('CINETPAY_SITE_ID'),
            'transaction_id' => $payment['reference'],
            'amount' => $amount,
            'currency' => 'XOF',
            'description' => 'Commande ' . preg_replace('/[^A-Za-z0-9 -]/', '', $order['number']),
            'notify_url' => $this->notifyUrl(),
            'return_url' => $this->returnUrl($payment, $orderToken),
            'channels' => 'CREDIT_CARD',
            'lang' => locale(),
            'customer_name' => mb_substr($order['customer_last_name'], 0, 60),
            'customer_surname' => mb_substr($order['customer_first_name'], 0, 60),
            'customer_email' => $order['customer_email'],
            'customer_phone_number' => preg_replace('/[^0-9+]/', '', $order['customer_phone']),
            'customer_address' => mb_substr((string) ($order['shipping_address'] ?? 'Katiola'), 0, 120),
            'customer_city' => mb_substr((string) ($order['shipping_city'] ?? 'Katiola'), 0, 60),
            'customer_country' => 'CI',
            'customer_state' => 'CI',
            'customer_zip_code' => '00225',
        ]);
        $url = $res['json']['data']['payment_url'] ?? null;
        if ($res['status'] !== 200 || ($res['json']['code'] ?? '') !== '201' || !is_string($url) || !str_starts_with($url, 'https://')) {
            $this->fail('initialisation du paiement', $res);
        }
        return ['redirect' => $url, 'pending' => false, 'provider_token' => (string) ($res['json']['data']['payment_token'] ?? '')];
    }

    public function fetchStatus(array $payment): ?array
    {
        $res = HttpClient::json('POST', $this->base() . '/v2/payment/check', [
            'apikey' => $this->env('CINETPAY_API_KEY'), 'site_id' => $this->env('CINETPAY_SITE_ID'), 'transaction_id' => $payment['reference'],
        ]);
        $d = $res['json']['data'] ?? null;
        if ($res['status'] !== 200 || !is_array($d)) {
            return null;
        }
        $status = match ($d['status'] ?? '') {
            'ACCEPTED' => 'succeeded',
            'REFUSED' => 'failed',
            default => 'pending',
        };
        if ($status === 'succeeded' && strtoupper((string) ($d['currency'] ?? 'XOF')) !== 'XOF') {
            return null;
        }
        return ['status' => $status, 'amount' => isset($d['amount']) ? (int) $d['amount'] : null, 'provider_reference' => isset($d['operator_id']) ? (string) $d['operator_id'] : null];
    }

    public function parseNotification(array $headers, string $body, array $post): array
    {
        $ref = is_string($post['cpm_trans_id'] ?? null) ? $post['cpm_trans_id'] : null;
        if ($ref === null || ($post['cpm_site_id'] ?? null) !== $this->env('CINETPAY_SITE_ID')) {
            throw new WebhookException('Contenu invalide.', 422);
        }
        $secret = $this->env('CINETPAY_SECRET_KEY');
        $sent = (string) (array_change_key_case($headers, CASE_LOWER)['x-token'] ?? '');
        if ($secret !== '') {
            $data = '';
            foreach (self::TOKEN_FIELDS as $f) {
                $data .= (string) ($post[$f] ?? '');
            }
            if (!hash_equals(hash_hmac('sha256', $data, $secret), $sent)) {
                // Non bloquant : l'état est de toute façon confirmé par /v2/payment/check (source faisant foi).
                Log::info('CinetPay : x-token non concordant, vérification par l\'API', ['ref' => $ref]);
            }
        }
        return ['reference' => $ref, 'token' => null, 'event' => null];
    }
}
