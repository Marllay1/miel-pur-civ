<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Contrat d'un prestataire de paiement en ligne.
 *
 * Règle de sécurité : une commande n'est marquée payée que sur un résultat dont l'authenticité est établie,
 * soit par une notification signée vérifiée (Wave, paiement simulé), soit par une interrogation serveur à serveur
 * du prestataire (fetchStatus). Une notification non signée ne sert qu'à déclencher cette vérification.
 */
interface Gateway
{
    public function code(): string;

    /** Libellé affiché (déjà traduit). */
    public function label(): string;

    /** Description courte affichée au client (déjà traduite). */
    public function description(): string;

    /** Vrai si le prestataire est activé en administration ET si toutes ses variables d'environnement sont présentes. */
    public function isEnabled(): bool;

    /** Variables d'environnement requises (seule leur présence est affichée, jamais leur valeur). */
    public function requiredEnv(): array;

    /** Le prestataire a-t-il besoin du numéro de téléphone du payeur (paiement poussé sur le mobile) ? */
    public function needsPhone(): bool;

    /**
     * Crée la transaction chez le prestataire.
     * @return array{redirect:?string, pending:bool, provider_token:?string}
     *   redirect : page de paiement hébergée par le prestataire ; pending : validation attendue sur le téléphone.
     * @throws PaymentException
     */
    public function start(array $payment, array $order, string $orderToken): array;

    /**
     * Interroge le prestataire (serveur à serveur) sur l'état réel du paiement.
     * @return ?array{status:string, amount:?int, provider_reference:?string} status ∈ succeeded|failed|cancelled|pending ; null si indisponible
     */
    public function fetchStatus(array $payment): ?array;

    /**
     * Lit une notification serveur à serveur.
     * @return array{reference:?string, token:?string, event:?array} event = résultat déjà authentifié (signature vérifiée) ou null
     * @throws WebhookException
     */
    public function parseNotification(array $headers, string $body, array $post): array;
}
