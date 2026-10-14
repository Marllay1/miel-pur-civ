<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\HttpClient;

/**
 * MTN Mobile Money — API Collections (momodeveloper.mtn.com).
 *  - POST /collection/token/ (Basic api_user:api_key + Ocp-Apim-Subscription-Key) → access_token
 *  - POST /collection/v1_0/requesttopay (X-Reference-Id UUID v4, X-Target-Environment, X-Callback-Url) → 202
 *    Le client valide la demande de paiement sur son téléphone.
 *  - GET  /collection/v1_0/requesttopay/{X-Reference-Id} → status PENDING|SUCCESSFUL|FAILED, amount, financialTransactionId
 * Le rappel (callback) n'est pas signé : il déclenche seulement une vérification par GET.
 * Valeurs à confirmer avec MTN Côte d'Ivoire : MTN_MOMO_API_BASE, MTN_MOMO_TARGET_ENV (sandbox : « sandbox », devise EUR).
 */
final class MtnMomoGateway extends AbstractGateway
{
    public function code(): string
    {
        return 'mtn_momo';
    }

    public function requiredEnv(): array
    {
        return ['MTN_MOMO_SUBSCRIPTION_KEY', 'MTN_MOMO_API_USER', 'MTN_MOMO_API_KEY', 'MTN_MOMO_TARGET_ENV'];
    }

    public function needsPhone(): bool
    {
        return true;
    }

    private function base(): string
    {
        return rtrim($this->env('MTN_MOMO_API_BASE', 'https://proxy.momoapi.mtn.com'), '/');
    }

    private function token(): string
    {
        return $this->cachedToken('collection', function (): array {
            $res = HttpClient::request('POST', $this->base() . '/collection/token/', [
                'Authorization' => 'Basic ' . base64_encode($this->env('MTN_MOMO_API_USER') . ':' . $this->env('MTN_MOMO_API_KEY')),
                'Ocp-Apim-Subscription-Key' => $this->env('MTN_MOMO_SUBSCRIPTION_KEY'),
                'Content-Length' => '0',
            ], '');
            $token = $res['json']['access_token'] ?? null;
            if ($res['status'] !== 200 || !is_string($token)) {
                $this->fail('jeton d\'accès', $res);
            }
            return [$token, (int) ($res['json']['expires_in'] ?? 3600)];
        });
    }

    /** Numéro au format international sans « + » (ex. 2250501020304). */
    public static function msisdn(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10) {
            $digits = '225' . $digits;
        }
        return preg_match('/^225\d{10}$/', $digits) ? $digits : null;
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public function start(array $payment, array $order, string $orderToken): array
    {
        $msisdn = self::msisdn((string) ($payment['payer_phone'] ?? ''));
        if ($msisdn === null) {
            throw new PaymentException(t('pay.phone_invalid'));
        }
        $uuid = self::uuid();
        $headers = [
            'Authorization' => 'Bearer ' . $this->token(),
            'X-Reference-Id' => $uuid,
            'X-Target-Environment' => $this->env('MTN_MOMO_TARGET_ENV'),
            'Ocp-Apim-Subscription-Key' => $this->env('MTN_MOMO_SUBSCRIPTION_KEY'),
        ];
        if (str_starts_with(base_url(), 'https://')) {
            $headers['X-Callback-Url'] = $this->notifyUrl();
        }
        $res = HttpClient::json('POST', $this->base() . '/collection/v1_0/requesttopay', [
            'amount' => (string) (int) $payment['amount'],
            'currency' => $this->env('MTN_MOMO_CURRENCY', 'XOF'),
            'externalId' => $payment['reference'],
            'payer' => ['partyIdType' => 'MSISDN', 'partyId' => $msisdn],
            'payerMessage' => mb_substr(t('pay.mtn_message', ['number' => $order['number']]), 0, 160),
            'payeeNote' => $order['number'],
        ], $headers);
        if ($res['status'] !== 202) {
            $this->fail('demande de paiement', $res);
        }
        return ['redirect' => null, 'pending' => true, 'provider_token' => $uuid];
    }

    public function fetchStatus(array $payment): ?array
    {
        $uuid = (string) ($payment['provider_token'] ?? '');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
            return null;
        }
        $res = HttpClient::request('GET', $this->base() . '/collection/v1_0/requesttopay/' . $uuid, [
            'Authorization' => 'Bearer ' . $this->token(),
            'X-Target-Environment' => $this->env('MTN_MOMO_TARGET_ENV'),
            'Ocp-Apim-Subscription-Key' => $this->env('MTN_MOMO_SUBSCRIPTION_KEY'),
        ]);
        if ($res['status'] !== 200 || !is_array($res['json'])) {
            return null;
        }
        $j = $res['json'];
        if (($j['externalId'] ?? null) !== $payment['reference']) {
            return null;
        }
        $status = match ($j['status'] ?? '') {
            'SUCCESSFUL' => 'succeeded',
            'FAILED', 'REJECTED', 'TIMEOUT' => 'failed',
            default => 'pending',
        };
        return ['status' => $status, 'amount' => isset($j['amount']) ? (int) $j['amount'] : null, 'provider_reference' => $j['financialTransactionId'] ?? null];
    }

    public function parseNotification(array $headers, string $body, array $post): array
    {
        $data = json_decode($body, true);
        $ref = is_array($data) && is_string($data['externalId'] ?? null) ? $data['externalId'] : null;
        $token = is_array($data) && is_string($data['referenceId'] ?? null) ? $data['referenceId'] : null;
        if ($ref === null && $token === null) {
            throw new WebhookException('Contenu invalide.', 422);
        }
        return ['reference' => $ref, 'token' => $token, 'event' => null];
    }
}
