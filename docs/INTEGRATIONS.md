# Intégrations — état réel

| Élément | État | Détail |
|---|---|---|
| Paiement à la livraison | **Opérationnel, testé** | Parcours complet testé (tests automatiques, navigateur réel sous Apache/XAMPP) ; activable par réglage et par zone |
| Wave | **Intégré, testé contre simulateur** | API Checkout. À valider avec les clés réelles (voir ci-dessous) |
| Orange Money | **Intégré, testé contre simulateur** | API Web Payment. À valider en bac à sable Orange |
| MTN Mobile Money | **Intégré, testé contre simulateur** | API Collections (demande de paiement validée sur le téléphone du client). À valider en bac à sable MTN |
| Carte bancaire | **Intégré via CinetPay, testé contre simulateur** | Page de paiement hébergée par CinetPay : aucun numéro de carte ne transite par ce serveur |
| Paiement SIMULÉ | **Désactivé par défaut** | Réservé aux tests (`DEMO_PAYMENT_ENABLED=1` + réglage). Étiqueté « SIMULÉ », aucune somme débitée |
| E-mails | **Opérationnel (SMTP), testé** | Client SMTP intégré : STARTTLS (587) ou TLS (465), authentification, message texte + HTML. Testé contre un serveur Mailpit local (15 contrôles) ; **envoi réel à valider avec vos identifiants** |
| WhatsApp | **Lien simple** | Bouton `wa.me` si un numéro est renseigné ; pas d'API WhatsApp Business |
| Mesure d'audience | **Prête, désactivée** | Plausible ou GA4, chargés uniquement après consentement ; CSP adaptée automatiquement |
| Newsletter | **Collecte seulement** | Inscriptions avec consentement + export CSV ; pas d'envoi de campagnes |

L'état de chaque moyen de paiement (actif, configuré mais désactivé, clés manquantes) et l'URL de notification à déclarer sont visibles dans **Administration → Intégrations**.

## Ce qui a été testé, et ce qui ne l'a pas été

- **Testé** : les quatre intégrations ont été exécutées de bout en bout contre un **simulateur local** (`tests/mock-providers/server.php`) qui reproduit les requêtes et réponses décrites par les documentations des prestataires : création du paiement, redirection, notification, vérification de l'état auprès du prestataire, succès, échec, rejeu, montant différent, signature invalide (scénario 28, 38 contrôles).
- **Non testé** : aucune transaction n'a été passée chez Wave, Orange, MTN ou CinetPay (ni bac à sable, ni production) : il faut les clés marchandes. Les points d'accès de Wave et CinetPay suivent leur documentation officielle publique ; pour **Orange Money** et **MTN MoMo**, le chemin de l'API, l'environnement cible et la devise varient selon le pays et le contrat et doivent être **confirmés avec Orange Côte d'Ivoire et MTN Côte d'Ivoire** (variables `ORANGE_MONEY_PATH`, `ORANGE_MONEY_CURRENCY`, `MTN_MOMO_API_BASE`, `MTN_MOMO_TARGET_ENV`).

**Avant d'ouvrir un moyen de paiement au public** : renseigner les clés dans `.env`, passer une transaction de faible montant en bac à sable puis en production, vérifier qu'elle apparaît « payée » dans la commande et que la notification est bien reçue (journal d'audit), tester un refus et une annulation.

## Sécurité des paiements (commune aux quatre prestataires)

- Le montant est toujours celui recalculé par le serveur (`payments.amount`) ; un paiement d'un montant différent n'est jamais validé.
- **Aucune notification n'est crue sur parole** : une notification non signée (MTN, Orange) ou dont la signature ne peut pas être vérifiée déclenche seulement une **vérification serveur à serveur** de l'état auprès du prestataire. Wave : signature `Wave-Signature` (HMAC-SHA256 de l'horodatage et du corps brut, tolérance 5 min). CinetPay : l'état est toujours confirmé par `/v2/payment/check`.
- Idempotence : chaque événement n'est traité qu'une fois (table `payment_events`) ; un rejeu est ignoré.
- Le retour du client sur le site (« je reviens de Wave ») ne valide rien : l'état est vérifié auprès du prestataire ; une page d'attente interroge le serveur tant que le paiement est en cours.
- Les commandes payables en ligne restées impayées expirent (`bin/expire-orders.php`, réglage *Délai de paiement*) et le stock est restitué.
- Aucune donnée de carte ni code secret Mobile Money n'est saisi ou stocké sur ce site.

## Paramétrage par prestataire

Les variables sont décrites dans `.env.example`. Un moyen de paiement n'est proposé que si **toutes** ses variables obligatoires sont renseignées **et** qu'il est coché dans *Administration → Réglages → Paiement*.

| Prestataire | Variables obligatoires | URL de notification à déclarer |
|---|---|---|
| Wave | `WAVE_API_KEY`, `WAVE_WEBHOOK_SECRET` (+ `WAVE_SIGNING_SECRET` si la clé API impose la signature des requêtes) | `https://votre-domaine/webhooks/paiement/wave` (événements `checkout.session.completed`, `checkout.session.payment_failed`) |
| Orange Money | `ORANGE_MONEY_CLIENT_ID`, `ORANGE_MONEY_CLIENT_SECRET`, `ORANGE_MONEY_MERCHANT_KEY`, `ORANGE_MONEY_PATH` | transmise automatiquement à chaque paiement (`notif_url`) |
| MTN MoMo | `MTN_MOMO_SUBSCRIPTION_KEY`, `MTN_MOMO_API_USER`, `MTN_MOMO_API_KEY`, `MTN_MOMO_TARGET_ENV` | transmise automatiquement (`X-Callback-Url`) ; le domaine doit être déclaré chez MTN |
| CinetPay (carte) | `CINETPAY_API_KEY`, `CINETPAY_SITE_ID`, `CINETPAY_SECRET_KEY` | transmise automatiquement (`notify_url`) |

Les notifications exigent un site en **HTTPS** accessible depuis Internet (pas `localhost`).

## Ajouter un autre prestataire

1. Créer `app/Services/Payment/<Prestataire>Gateway.php` qui étend `AbstractGateway` (interface `Gateway`) : `requiredEnv()`, `start()` (création du paiement, renvoie l'URL de la page hébergée), `fetchStatus()` (état lu chez le prestataire), `parseNotification()` (signature vérifiée, sinon simple demande de vérification).
2. L'ajouter dans `PaymentService::gateways()`, avec un réglage `<code>_enabled` et les libellés `pay.<code>` / `pay.desc.<code>` en français et en anglais.
3. Ajouter son comportement au simulateur `tests/mock-providers/server.php` et des cas au scénario 28.
