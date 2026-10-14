# Intégrations — état honnête

| Élément | État | Détail |
|---|---|---|
| Paiement à la livraison | **Opérationnel, testé** | Parcours complet testé (HTTP en processus et navigateur réel) ; activable par réglage et par zone |
| Paiement SIMULÉ | **Opérationnel, testé — à désactiver en production** | Étiqueté « SIMULÉ » partout ; notification signée HMAC-SHA256, tolérance 300 s, rejeu ignoré, montant contrôlé |
| Orange Money | **Non intégré** | Jamais proposé aux clients. Requis : contrat marchand, API Web Payment, accès bac à sable |
| MTN Mobile Money | **Non intégré** | Idem (API MoMo Collections) |
| Wave | **Non intégré** | Idem (Wave Checkout / Business) |
| Carte bancaire | **Non intégré** | Passer par un agrégateur (page de paiement hébergée) ; aucun numéro de carte ne doit transiter par ce serveur |
| E-mails | **Partiel** | Pilote `log` (fichiers, développement) ou `mail()` (nécessite un MTA configuré). SMTP authentifié non intégré |
| WhatsApp | **Lien simple** | Bouton `wa.me` si un numéro est renseigné ; pas d'API WhatsApp Business |
| Mesure d'audience | **Prête, désactivée** | Plausible ou GA4, chargés uniquement après consentement ; CSP adaptée automatiquement |
| Newsletter | **Collecte seulement** | Inscriptions avec consentement + export CSV ; pas d'envoi de campagnes |
| Pages légales | **Modèles provisoires** | Avertissement affiché tant qu'elles ne sont pas validées en administration |

## Intégrer un prestataire de paiement

1. Créer `app/Services/Payment/<Prestataire>Gateway.php` implémentant `Gateway` :
   - `isEnabled()` : vrai seulement si toutes les variables d'environnement sont présentes **et** un réglage d'activation est coché ;
   - `checkoutUrl()` : créer la transaction chez le prestataire (montant = `payments.amount`, référence = `payments.reference`) et renvoyer l'URL de paiement hébergée ;
   - `parseWebhook()` : vérifier la signature selon la documentation officielle, contrôler l'horodatage, renvoyer `event_id`, `reference`, `status` (`succeeded`/`failed`/`cancelled`), `amount`.
2. Remplacer l'entrée `UnconfiguredGateway` correspondante dans `PaymentService::gateways()`.
3. Déclarer l'URL de notification chez le prestataire : `https://domaine/webhooks/paiement/<code>`.
4. `PaymentService::applyResult()` gère déjà l'idempotence (table `payment_events`), le contrôle du montant, les échecs et le paiement reçu sur une commande close (audit pour traitement manuel).
5. Tester en bac à sable (succès, échec, annulation, rejeu, signature invalide, montant différent) avant toute activation.
