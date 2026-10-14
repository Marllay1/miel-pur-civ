<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\HttpClient;

/**
 * Wave Checkout API (docs.wave.com/checkout, docs.wave.com/webhook).
 *  - POST /v1/checkout/sessions → wave_launch_url (page Wave), id « cos-… »
 *  - GET  /v1/checkout/sessions/{id} → payment_status (processing|cancelled|succeeded), checkout_status (open|complete|expired)
 *  - Webhook signé : en-tête Wave-Signature « t=<ts>,v1=<hmac> », HMAC-SHA256(ts . corps brut, secret webhook)
 * Montants XOF : chaînes d'entiers.
 */
final class WaveGateway extends AbstractGateway
{
    public const TOLERANCE = 300;

    public function code(): string
    {
        return 'wave';
    }

    public function requiredEnv(): array
    {
        return ['WAVE_API_KEY', 'WAVE_WEBHOOK_SECRET'];
    }

    private function base(): string
    {
        return rtrim($this->env('WAVE_API_BASE', 'https://api.wave.com'), '/');
    }

    /** En-têtes ; signature de requête si la clé API a été créée avec « request signing » (WAVE_SIGNING_SECRET). */
    private function headers(string $body): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->env('WAVE_API_KEY'), 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $signing = $this->env('WAVE_SIGNING_SECRET');
        if ($signing !== '') {
            $ts = (string) time();
            $headers['Wave-Signature'] = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . $body, $signing);
        }
        return $headers;
    }

    public function start(array $payment, array $order, string $orderToken): array
    {
        $body = json_encode([
            'amount' => (string) (int) $payment['amount'],
            'currency' => 'XOF',
            'success_url' => $this->returnUrl($payment, $orderToken),
            'error_url' => $this->returnUrl($payment, $orderToken, 'echec'),
            'client_reference' => $payment['reference'],
        ], JSON_UNESCAPED_SLASHES);
        $res = HttpClient::request('POST', $this->base() . '/v1/checkout/sessions', $this->headers($body), $body);
        $url = $res['json']['wave_launch_url'] ?? null;
        $id = $res['json']['id'] ?? null;
        if ($res['status'] < 200 || $res['status'] >= 300 || !is_string($url) || !is_string($id) || !str_starts_with($url, 'https://')) {
            $this->fail('création de session', $res);
        }
        return ['redirect' => $url, 'pending' => false, 'provider_token' => $id];
    }

    public function fetchStatus(array $payment): ?array
    {
        $id = (string) ($payment['provider_token'] ?? '');
        if (!preg_match('/^cos-[A-Za-z0-9_-]{1,40}$/', $id)) {
            return null;
        }
        $res = HttpClient::request('GET', $this->base() . '/v1/checkout/sessions/' . rawurlencode($id), $this->headers(''));
        if ($res['status'] !== 200 || !is_array($res['json'])) {
            return null;
        }
        $s = $res['json'];
        if (($s['client_reference'] ?? null) !== $payment['reference']) {
            return null;
        }
        $status = match (true) {
            ($s['payment_status'] ?? '') === 'succeeded' => 'succeeded',
            ($s['checkout_status'] ?? '') === 'expired' => 'cancelled',
            default => 'pending',
        };
        return ['status' => $status, 'amount' => isset($s['amount']) ? (int) $s['amount'] : null, 'provider_reference' => $s['transaction_id'] ?? null];
    }

    public function parseNotification(array $headers, string $body, array $post, ?int $now = null): array
    {
        $now ??= time();
        $headers = array_change_key_case($headers, CASE_LOWER);
        $sig = (string) ($headers['wave-signature'] ?? '');
        $ts = null;
        $candidates = [];
        foreach (explode(',', $sig) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't' && ctype_digit($v)) {
                $ts = (int) $v;
            } elseif ($k === 'v1' && $v !== '') {
                $candidates[] = $v;
            }
        }
        if ($ts === null || $candidates === []) {
            throw new WebhookException('Signature absente.', 400);
        }
        if (abs($now - $ts) > self::TOLERANCE) {
            throw new WebhookException('Horodatage expiré.', 400);
        }
        $expected = hash_hmac('sha256', $ts . $body, $this->env('WAVE_WEBHOOK_SECRET'));
        $ok = false;
        foreach ($candidates as $c) {
            $ok = $ok || hash_equals($expected, $c);
        }
        if (!$ok) {
            throw new WebhookException('Signature invalide.', 401);
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !is_string($data['id'] ?? null) || !is_array($data['data'] ?? null)) {
            throw new WebhookException('Contenu invalide.', 422);
        }
        $d = $data['data'];
        $status = match ($data['type'] ?? '') {
            'checkout.session.completed' => ($d['payment_status'] ?? '') === 'succeeded' ? 'succeeded' : null,
            'checkout.session.payment_failed' => 'failed',
            default => null,
        };
        $reference = is_string($d['client_reference'] ?? null) ? $d['client_reference'] : null;
        if ($status === null || $reference === null) {
            return ['reference' => null, 'token' => null, 'event' => null];
        }
        return ['reference' => $reference, 'token' => null, 'event' => [
            'event_id' => mb_substr($data['id'], 0, 100), 'reference' => $reference, 'status' => $status,
            'amount' => isset($d['amount']) ? (int) $d['amount'] : 0, 'provider_reference' => $d['transaction_id'] ?? null,
        ]];
    }
}
