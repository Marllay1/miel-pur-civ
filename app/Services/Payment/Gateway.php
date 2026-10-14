<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Contrat d'un prestataire de paiement en ligne.
 * Pour intégrer Orange Money / MTN MoMo / Wave / carte : créer une classe implémentant cette interface,
 * l'enregistrer dans PaymentService::gateways(), et ne la rendre « configurée » qu'après tests en bac à sable.
 */
interface Gateway
{
    public function code(): string;

    public function label(): string;

    /** Description courte affichée au client lors du choix. */
    public function description(): string;

    /** Vrai uniquement si le prestataire est intégré, configuré et autorisé à être proposé. */
    public function isEnabled(): bool;

    /** Variables d'environnement requises (affichées dans l'administration, jamais leurs valeurs). */
    public function requiredEnv(): array;

    /** URL vers laquelle rediriger le client pour payer. */
    public function checkoutUrl(array $payment, string $orderToken): string;

    /**
     * Vérifie et décode une notification serveur à serveur.
     * @return array{event_id:string, reference:string, status:string, amount:int, provider_reference:?string}
     * @throws WebhookException
     */
    public function parseWebhook(array $headers, string $body, ?int $now = null): array;
}
