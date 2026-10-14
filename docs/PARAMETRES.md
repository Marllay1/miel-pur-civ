# Paramètres à renseigner avant la mise en production

Rien de ce qui suit ne doit être inventé : chaque information doit être fournie et validée par MIEL PUR DE CÔTE D'IVOIRE.

## Fichier `.env` (serveur)

| Variable | Valeur attendue |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `0` |
| `APP_URL` | `https://` + domaine définitif |
| `APP_KEY` | générée par `bin/install.php` (ne jamais la publier ni la changer après mise en ligne) |
| `DB_*` | accès à la base de production (utilisateur dédié, mot de passe fort) |
| `MAIL_DRIVER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `mail` si le serveur sait envoyer des e-mails ; adresse d'expédition du domaine |
| `DEMO_PAYMENT_ENABLED` | `0` |
| `TRUSTED_PROXY` | IP du proxy/CDN éventuel, sinon vide |

## Administration → Réglages

- Identité : adresse, téléphone, e-mail public, horaires, raison sociale, **RCCM**, **NCC**, numéro WhatsApp, réseaux sociaux.
- Commerce : décocher *Bandeau démonstration* et *Paiement SIMULÉ* ; montant minimum de commande ; délai de paiement ; seuil de stock faible ; **e-mail de notification des nouvelles commandes**.
- Mesure d'audience : seulement si souhaitée (chargée après consentement du visiteur).

## Administration → contenus et catalogue

- Supprimer (ou remplacer) les **5 produits de démonstration** et leurs illustrations ; saisir la gamme réelle, les prix, les stocks, les photos (avec texte alternatif).
- **Zones de livraison** : tarifs, seuils de gratuité, délais, possibilité de paiement à la livraison par zone (actuellement marqués `[À CONFIRMER]`).
- **Accueil** et **À propos** : histoire, ruchers, méthodes — informations vérifiées uniquement. Aucune certification ne doit être mentionnée sans justificatif.
- **Pages légales** (CGV, confidentialité, livraison, retours, mentions légales) : faire relire par un juriste (droit ivoirien : transactions électroniques, protection des données personnelles, ARTCI le cas échéant), puis cocher *validé*.
- **FAQ** : relire les réponses marquées `[À CONFIRMER]`.
- Code promo de démonstration `DEMO10` : désactiver.
- Logo et couleurs définitifs : le logo actuel (alvéole + goutte) est une proposition graphique à valider.

La page **/admin/integrations** récapitule automatiquement les points encore ouverts.
