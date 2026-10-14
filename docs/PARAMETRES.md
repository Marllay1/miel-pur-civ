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

- **Identité** : téléphone (renseigné : +225 07 47 09 90 06, numéro « Mobile » de la page Facebook ; l'annuaire Les Pages Vertes indiquait +225 01 71 61 71 52 — à confirmer), WhatsApp (la page Facebook indique « 88 79 40 37 », ancien format à 8 chiffres : numéro actuel à 10 chiffres à confirmer), e-mail public, adresse, horaires, **raison sociale**, **RCCM**, **NCC**, **directeur de la publication**, **hébergeur** (mentions légales obligatoires), numéro WhatsApp, réseaux sociaux.
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

## Écarts relevés avec la page Facebook (consultée le 14/10/2026)

La page Facebook n'est que partiellement lisible sans compte (nom, présentation abrégée, coordonnées, une dizaine de photos). À trancher par la boutique :

- **Conditionnements** : les photos montrent du miel en **bouteilles** (étiquette « Miel pur de Côte d'Ivoire ») et du **miel en rayon** en barquettes, alors que le catalogue propose des pots de 250 g / 500 g / 1 kg. Indiquer les conditionnements et prix réellement vendus ; le miel en rayon et les bouteilles ne sont pas au catalogue faute de prix vérifiés.
- **Paniers cadeaux** : visibles sur une photo de stand ; le « Coffret découverte » du catalogue est-il ce produit ?
- **Présentation** : seule la première phrase de la page est lisible (« Promouvoir les vertus du Miel entièrement produit en Côte d'Ivoire… ») ; elle est reprise dans « Notre démarche ». Le texte complet, l'histoire et les valeurs sont à fournir.
- **Horaires** : la page indique « Ouvre bientôt », sans horaires.
- **Avis** : la page affiche « Recommandé par 100 % (21 avis) » ; ces avis ne sont pas repris sur le site (non vérifiables individuellement).
- **Photos** : voir [IDENTITE-VISUELLE.md](IDENTITE-VISUELLE.md) — originaux en haute définition et photos des ruchers à fournir.
