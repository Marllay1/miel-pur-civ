<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Emplacement réservé pour un prestataire NON INTÉGRÉ (Orange Money, MTN MoMo, Wave, carte).
 * N'est jamais proposé aux clients : isEnabled() renvoie toujours false.
 * L'intégration réelle exige : contrat marchand, documentation API officielle, accès bac à sable,
 * tests de bout en bout, puis remplacement de cette classe par une implémentation dédiée.
 */
final class UnconfiguredGateway implements Gateway
{
    public function __construct(
        private string $code,
        private string $label,
        private array $env,
        private string $notes = '',
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return $this->notes;
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function requiredEnv(): array
    {
        return $this->env;
    }

    public function checkoutUrl(array $payment, string $orderToken): string
    {
        throw new \LogicException($this->label . ' n\'est pas intégré.');
    }

    public function parseWebhook(array $headers, string $body, ?int $now = null): array
    {
        throw new WebhookException($this->label . ' n\'est pas intégré.', 404);
    }
}
