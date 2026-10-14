<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Env;
use App\Services\Settings;

/**
 * Paiement SIMULÉ (tests uniquement). Aucune somme n'est débitée.
 * Notification signée HMAC-SHA256 de « horodatage.corps », tolérance 300 s.
 * Désactivé par défaut : DEMO_PAYMENT_ENABLED=1 + réglage demo_payment_enabled + secret ≥ 32 caractères.
 */
final class DemoGateway extends AbstractGateway
{
    public const TOLERANCE = 300;

    public function code(): string
    {
        return 'demo';
    }

    public function secret(): string
    {
        return (string) Env::get('DEMO_PAYMENT_SECRET', '');
    }

    public function isEnabled(): bool
    {
        return Env::bool('DEMO_PAYMENT_ENABLED') && strlen($this->secret()) >= 32 && Settings::bool('demo_payment_enabled');
    }

    public function requiredEnv(): array
    {
        return ['DEMO_PAYMENT_ENABLED', 'DEMO_PAYMENT_SECRET'];
    }

    public function start(array $payment, array $order, string $orderToken): array
    {
        return ['redirect' => url('/paiement/demo/' . $payment['reference'], ['t' => $orderToken]), 'pending' => false, 'provider_token' => null];
    }

    public function fetchStatus(array $payment): ?array
    {
        return null;
    }

    public function sign(string $body, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $this->secret());
    }

    /** @return array{headers:array, body:string} notification signée, comme l'enverrait un prestataire */
    public function buildNotification(array $payment, string $status, ?int $timestamp = null): array
    {
        $timestamp ??= time();
        $body = json_encode([
            'event_id' => 'evt_' . bin2hex(random_bytes(12)),
            'reference' => $payment['reference'],
            'status' => $status,
            'amount' => (int) $payment['amount'],
            'currency' => 'XOF',
            'provider_reference' => 'SIM-' . strtoupper(bin2hex(random_bytes(5))),
        ], JSON_THROW_ON_ERROR);
        return [
            'headers' => ['x-demo-timestamp' => (string) $timestamp, 'x-demo-signature' => $this->sign($body, $timestamp)],
            'body' => $body,
        ];
    }

    public function parseNotification(array $headers, string $body, array $post, ?int $now = null): array
    {
        $now ??= time();
        $headers = array_change_key_case($headers, CASE_LOWER);
        $ts = $headers['x-demo-timestamp'] ?? null;
        $sig = $headers['x-demo-signature'] ?? null;
        if ($this->secret() === '') {
            throw new WebhookException('Prestataire non configuré.', 503);
        }
        if (!is_string($ts) || !is_string($sig) || !ctype_digit($ts)) {
            throw new WebhookException('Signature absente.', 400);
        }
        if (abs($now - (int) $ts) > self::TOLERANCE) {
            throw new WebhookException('Horodatage expiré.', 400);
        }
        if (!hash_equals($this->sign($body, (int) $ts), $sig)) {
            throw new WebhookException('Signature invalide.', 401);
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['event_id'], $data['reference'], $data['status'], $data['amount'])
            || !is_string($data['event_id']) || !is_string($data['reference']) || !is_int($data['amount'])
            || !in_array($data['status'], ['succeeded', 'failed', 'cancelled'], true)) {
            throw new WebhookException('Contenu invalide.', 422);
        }
        return ['reference' => $data['reference'], 'token' => null, 'event' => [
            'event_id' => mb_substr($data['event_id'], 0, 100),
            'reference' => $data['reference'],
            'status' => $data['status'],
            'amount' => $data['amount'],
            'provider_reference' => isset($data['provider_reference']) && is_string($data['provider_reference']) ? mb_substr($data['provider_reference'], 0, 120) : null,
        ]];
    }
}
