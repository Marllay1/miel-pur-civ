# Guide d'administration

Accès : `/admin/connexion`. Session fermée après **2 heures d'inactivité**. Chaque action sensible est inscrite au **journal d'audit**.

## Rôle

Un seul rôle d'équipe : **Administrateur**, avec accès à toute l'administration (catalogue, stock, promotions, commandes, devis, clients, contenus, réglages, intégrations, équipe, journal). Les clients n'ont aucun accès à l'administration.

*Équipe* : ajouter un administrateur (mot de passe provisoire à lui transmettre de façon sûre) ou désactiver le compte d'une personne qui quitte l'équipe. On ne peut pas désactiver son propre compte, et il reste toujours au moins un administrateur actif. Les droits sont vérifiés par le serveur sur chaque page et chaque action, pas seulement dans le menu.

## Traiter une commande

1. *Commandes* → onglet **En attente de confirmation** (paiement à la livraison).
2. Ouvrir la commande, **appeler le client** au numéro affiché pour confirmer adresse et créneau.
3. *Changer le statut* → **Confirmer / mettre en préparation**. Le client reçoit un e-mail.
4. Renseigner *Livreur / N° de suivi* si utile, puis **Marquer comme expédiée**, enfin **Marquer comme livrée** (pour un paiement à la livraison, la commande passe automatiquement en « payée »).
5. Annulation : **Annuler la commande** restitue automatiquement le stock et l'utilisation du code promo. Remboursement : possible uniquement si la commande a été payée.

Les transitions impossibles (par exemple « livrée » sans passer par « expédiée ») sont refusées. Les **notes internes** ne sont jamais visibles du client ; le *commentaire* de changement de statut l'est. L'export CSV reprend les filtres affichés.

## Catalogue

- **Produit** : nom, résumé, description (mise en forme simple : ligne vide = paragraphe, `## ` titre, `- ` liste, `**gras**`), origine, utilisations, conservation, SEO. Case *Produit de démonstration* = badge « Démo ».
- **Variantes** : chaque format (250 g, 500 g, 1 kg, seau…) a sa référence (SKU), son prix en FCFA entiers et son stock. Un produit sans variante active n'apparaît pas sur le site.
- **Version anglaise** : les fiches produit, catégories, pages, questions de FAQ et zones de livraison ont un encadré *Traduction anglaise* ; chaque format a un champ *Libellé (EN)*. Un champ vide affiche le texte français sur la version anglaise du site (`/en/…`). Les textes de l'accueil se traduisent dans *Contenus → Accueil*. L'administration elle-même reste en français.
- **Images** : JPEG, PNG ou WebP, 5 Mo maximum, ré-encodées en WebP ; le *texte alternatif* (description de l'image) est obligatoire.
- **Suppression** : le produit est archivé ; les commandes passées conservent nom et prix d'origine.
- **Stock** : page *Stock* → nouvelle quantité + motif (inventaire, réception, casse). Toutes les variations sont historisées. Si une vente a lieu pendant la saisie, l'ajustement est refusé pour éviter d'écraser la vente.
- **Codes promo** : pourcentage, montant fixe ou livraison offerte ; panier minimum, dates, nombre maximal d'utilisations.

## Contenus

- **Pages et légal** : modifier le texte des pages. Cocher *Texte validé juridiquement* uniquement après relecture par un juriste ; jusque-là, un avertissement « document provisoire » est affiché.
- **Avis** : publier ou refuser. Ne jamais publier d'avis inventé ni modifier le texte d'un client.
- **Accueil** : titres et textes ; conserver les marqueurs `[À COMPLÉTER]` tant que l'information n'est pas vérifiée (ils sont surlignés sur le site).

## Paramètres du site (`/admin/parametres`)

Toutes les informations courantes se modifient ici, sans toucher au code ; chaque valeur est enregistrée en base de données et reprise automatiquement par toutes les pages (en-tête, pied de page, contact, fiche produit, panier, e-mails, données structurées). Chaque section s'enregistre séparément ; si une valeur est invalide, **rien n'est enregistré** et le champ fautif est signalé. Chaque modification est inscrite au journal d'audit.

| Section | Contenu |
|---|---|
| Identité de l'entreprise | nom commercial (FR/EN), slogan, présentation courte du pied de page |
| Logos et images | logo de l'en-tête (clair/sombre), logo du pied de page (clair/sombre), favicon, image de partage, image d'ouverture de l'accueil. Téléversement PNG/JPEG/WebP (5 Mo max), **aperçu avant envoi**, dimensions minimales contrôlées par le serveur, image ré-encodée ; remplacement (l'ancien fichier est supprimé) et suppression avec confirmation (retour au logo installé). Le favicon doit être carré : toutes les tailles d'icônes sont générées automatiquement |
| Coordonnées et contact | indicatif pays, téléphones principal et secondaire, WhatsApp (numéro, message prérempli FR/EN, activation ; le lien `wa.me` est généré automatiquement), e-mails public et commercial, adresse, ville, pays, horaires, lien Google Maps ; chaque bloc peut être affiché ou masqué |
| Réseaux sociaux | Facebook, Instagram, TikTok, YouTube, LinkedIn, autre lien (avec son nom) : adresse **https** vérifiée, case d'affichage, aperçu de l'icône ; option d'affichage dans l'en-tête |
| Apparence | couleurs principale, secondaire, d'accent et de fond (sélecteur), polices parmi une liste fermée ; éléments affichés dans l'en-tête et le pied de page. Les **contrastes sont vérifiés** (WCAG AA, 4,5:1) avant l'enregistrement ; bouton **Aperçu** (nouvel onglet, rien n'est publié). Aucun CSS libre |
| Langues | anglais activé ou non, langue par défaut, détection de la langue du navigateur |
| Livraison, paiements et commandes | e-mail de notification des commandes, minimum de commande, délai de paiement, seuil de stock faible, consignes de livraison ; liens vers *Moyens de paiement*, *Zones de livraison*, *Devises*, *Intégrations* |
| Référencement et partage | titre et description du site (FR/EN), mesure d'audience |
| Politiques et mentions légales | raison sociale, RCCM, NCC, directeur de la publication, hébergeur ; liens vers *Pages et légal* et *FAQ* |

## Moyens de paiement (`/admin/paiements`)

Pour chaque moyen : activation, affichage du logo, logo personnalisé (téléversement), nom et description (FR/EN), ordre d'affichage, et pour un paiement manuel : numéro de réception, numéro marchand, bénéficiaire, instructions (FR/EN). L'état indique honnêtement si le moyen est **proposé aux clients** :

- **paiement à la livraison** : proposé s'il est activé (et autorisé par la zone de livraison) ;
- **prestataire intégré** (Wave, Orange Money, MTN MoMo, carte via CinetPay) : proposé seulement s'il est activé **et** que ses clés sont présentes dans `.env` (les clés manquantes sont listées) ;
- **paiement manuel** (Moov Money, virement, autre moyen ajouté) : proposé seulement s'il est activé **et** qu'un numéro de réception ou des instructions sont renseignés. Le client voit les instructions après la commande et dans l'e-mail ; la commande reste *en attente de paiement* jusqu'à ce que l'équipe vérifie la réception et passe la commande à « Payée ».

Un logo n'est **jamais** affiché pour un moyen non proposé (pied de page « Paiements acceptés », panier, étape de paiement, accueil). Un moyen manuel jamais utilisé peut être supprimé ; les moyens intégrés ne peuvent être que désactivés.

## Carrousel de l'accueil (`/admin/carrousel`)

Diapositives affichées directement sous l'en-tête : image (1200 × 500 minimum) et image mobile facultative (600 × 700 minimum), texte alternatif, titre, texte, bouton et lien (page du site `/…` ou adresse `https://`), ordre (flèches ↑ ↓), activation, période de publication facultative. *Aperçu (toutes)* montre aussi les diapositives désactivées. Réglages : défilement automatique et durée (4 à 15 s). Sans diapositive publiée, l'accueil affiche son image d'ouverture. N'annoncer que des offres réellement disponibles.

## Devises d'affichage (`/admin/devises`)

Les prix et le paiement restent **en francs CFA**. Une devise activée (euro, dollar…) affiche seulement un **équivalent indicatif** à côté du prix (fiche produit, panier), avec le taux saisi par l'équipe ; une devise sans taux ne peut pas être activée. Pas de conversion automatique selon la localisation : l'euro a une parité fixe (655,957 FCFA), les autres taux sont à mettre à jour régulièrement. Un client à l'étranger ne peut commander que si une zone de livraison couvre son adresse.

## Zones de livraison

Frais, seuil de gratuité, délai, paiement à la livraison autorisé, retrait.

## Intégrations

La page *Intégrations* indique honnêtement ce qui fonctionne et ce qui reste à configurer, et présente la **liste de contrôle avant mise en production**.
