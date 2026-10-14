# Paramètres à renseigner avant la mise en production

Rien de ce qui suit ne doit être inventé : chaque information doit être fournie et validée par MIEL PUR DE CÔTE D'IVOIRE. Une information laissée vide n'est simplement pas affichée sur le site.

## Fichier `.env` (serveur)

Toutes les variables sont décrites dans `.env.example`.

| Variable | Valeur attendue |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `0` |
| `APP_URL` | `https://` + domaine définitif |
| `APP_KEY` | générée par `bin/install.php` (ne jamais la publier ni la changer après mise en ligne) |
| `DB_*` | accès à la base de production (utilisateur dédié, mot de passe fort) |
| `DEMO_PAYMENT_ENABLED` | `0` |
| `TRUSTED_PROXY` | IP du proxy/CDN éventuel, sinon vide |

### E-mails (SMTP)

Identifiants fournis par l'hébergeur de la messagerie (ou un service comme Brevo, Mailjet, Google Workspace…). L'adresse d'expédition doit appartenir au domaine de la boutique, et le domaine doit avoir des enregistrements **SPF** et **DKIM** (sinon les messages risquent d'arriver en indésirables).

| Variable | Valeur |
|---|---|
| `MAIL_DRIVER` | `smtp` |
| `MAIL_FROM_ADDRESS` | ex. `commandes@votre-domaine.ci` |
| `MAIL_FROM_NAME` | `Miel Pur de Côte d'Ivoire` |
| `SMTP_HOST` | serveur SMTP du fournisseur |
| `SMTP_PORT` / `SMTP_ENCRYPTION` | `587` / `tls` (STARTTLS), ou `465` / `ssl` |
| `SMTP_USERNAME` / `SMTP_PASSWORD` | identifiants SMTP (jamais versionnés) |
| `SMTP_ALLOW_SELF_SIGNED` | `0` |

Vérification : `php tests/smtp-check.php --to=votre@adresse.ci` (le message doit arriver en boîte de réception). Le mot de passe n'apparaît jamais dans les journaux.

### Paiements en ligne

Clés Wave, Orange Money, MTN MoMo et CinetPay (carte) : voir [INTEGRATIONS.md](INTEGRATIONS.md). Sans clés, le moyen de paiement n'est tout simplement pas proposé ; le paiement à la livraison reste disponible.

## Administration → Réglages

- **Identité** : téléphone (renseigné : +225 01 71 61 71 52), e-mail public, adresse, horaires, **raison sociale**, **RCCM**, **NCC**, **directeur de la publication**, **hébergeur** (mentions légales obligatoires), numéro WhatsApp, réseaux sociaux.
- **Commerce** : **e-mail de notification des nouvelles commandes** (sans lui, la boutique n'est pas prévenue des commandes par e-mail), montant minimum de commande, délai de paiement, seuil de stock faible.
- **Paiement** : cocher les moyens de paiement en ligne une fois leurs clés installées et testées ; *Paiement SIMULÉ* doit rester décoché.
- **Mesure d'audience** : seulement si souhaitée (chargée après consentement du visiteur).

## Administration → contenus et catalogue

- **Catalogue** : la gamme et les prix installés sont ceux confirmés par la boutique (5 produits, 10 formats). Saisir les **stocks réels** et, si possible, de vraies **photos** (avec texte alternatif) à la place des illustrations. Deux écarts avec les prix publiés dans l'annuaire Les Pages Vertes sont à vérifier : cire 1 kg à 12 000 FCFA dans le catalogue contre 7 000 FCFA dans l'annuaire ; miel 1 L à 5 000 FCFA dans l'annuaire, alors que le catalogue propose le miel 1 kg à 8 000 FCFA. Si les prix du catalogue sont les bons, mettre à jour la fiche de l'annuaire.
- **Zones de livraison** : tarifs, seuils de gratuité et délais installés à vérifier (Retrait Katiola, Katiola, Bouaké, Abidjan, autres villes).
- **Accueil** et **À propos** : textes en français et en anglais modifiables (*Contenus → Accueil*). Les mentions « Miel 100 % BIO » et « certifié conforme aux normes internationales par un laboratoire habilité par l'INAO » sont affichées à la demande de la boutique, qui doit pouvoir présenter les justificatifs.
- **Pages légales** (CGV, confidentialité, livraison, retours, mentions légales) : faire relire par un juriste (droit ivoirien : loi n° 2013-546 sur les transactions électroniques, loi n° 2013-450 sur la protection des données à caractère personnel, ARTCI), puis cocher *validé*.
- **Traductions anglaises** : chaque fiche (produit, format, catégorie, page, question de FAQ, zone) a un encadré *Traduction anglaise* ; un champ laissé vide affiche le texte français sur la version anglaise.
- Code promo `BIENVENUE10` : installé **inactif** ; l'activer seulement si l'offre est décidée.

La page **/admin/integrations** récapitule automatiquement les points encore ouverts.
