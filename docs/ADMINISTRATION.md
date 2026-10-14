# Guide d'administration

Accès : `/admin/connexion`. Session fermée après **2 heures d'inactivité**. Chaque action sensible est inscrite au **journal d'audit**.

## Rôle

Un seul rôle d'équipe : **Administrateur**, avec accès à toute l'administration (catalogue, stock, promotions, commandes, devis, clients, contenus, réglages, intégrations, équipe, journal). Les clients n'ont aucun accès à l'administration.

*Équipe* : ajouter un administrateur (mot de passe provisoire à lui transmettre de façon sûre) ou désactiver le compte d'une personne qui quitte l'équipe. On ne peut pas désactiver son propre compte, et il reste toujours au moins un administrateur actif. Les droits sont vérifiés par le serveur sur chaque page et chaque action, pas seulement dans le menu.

## Traiter une commande

1. *Commandes* → onglet **En attente de confirmation** (paiement à la livraison).
2. Ouvrir la commande, **appeler le client** au numéro affiché pour confirmer adresse et créneau.
3. *Changer le statut* → **Confirmer / mettre en préparation**. Le client reçoit un e-mail.
4. Renseigner *Livreur / N° de suivi* si utile, puis **Marquer comme expédiée**, enfin **Marquer comme livrée**. Pour un paiement à la livraison, cocher **« encaissement confirmé »** en marquant la commande livrée (ou enregistrer l'encaissement ensuite) : sans cette confirmation, la commande reste « à encaisser » et n'est jamais considérée comme payée. Le récapitulatif imprimable indique au livreur le montant à encaisser.
5. Annulation : **Annuler la commande** restitue automatiquement le stock et l'utilisation du code promo. Un montant déjà encaissé (acompte…) n'est jamais conservé automatiquement : la commande signale « remboursement à traiter ».

### Localisation de livraison

La fiche commande distingue l'**adresse saisie par le client** (avec ses instructions) de la **position GPS** qu'il a éventuellement transmise (coordonnées, précision, marqueur déplacé, liens OpenStreetMap et Google Maps). Réglages et services externes : [LOCALISATION.md](LOCALISATION.md). Proposition pour le suivi des livreurs : [SUIVI-LIVRAISONS.md](SUIVI-LIVRAISONS.md).

### Paiement d'une commande (encadré *Paiement*)

- Conditions : paiement intégral, **acompte + solde**, à la livraison ou **sur facture** (professionnel autorisé) ; type de client (particulier / professionnel) ; commande en gros.
- Montants calculés par le serveur : total dû, livraison, acompte demandé, **encaissé**, remboursé, **reste à payer**, échéance.
- **Enregistrer un encaissement constaté** : montant (jamais plus que le reste dû), moyen (Mobile Money, espèces, virement…), référence de la transaction. À faire uniquement après avoir vu le paiement sur le relevé : une déclaration ou une capture d'écran du client n'est pas une preuve. Quand le client signale un paiement manuel (« J'ai effectué le paiement »), la commande passe en « paiement en cours de vérification » et l'équipe est prévenue.
- L'enregistrement fait avancer la commande : en attente de paiement → **acompte reçu** → payée. Les paiements en ligne vérifiés chez le prestataire s'inscrivent seuls.
- **Rembourser** : montant (au plus l'encaissé non remboursé), **motif obligatoire**, moyen, référence ; « déjà effectué » ou « à effectuer » (puis *Marquer effectué*). Remboursement total : la commande passe en « remboursée » ; partiel : « partiellement remboursée ».
- Expédition : refusée tant que le paiement intégral anticipé n'est pas encaissé ; avec un acompte, selon les Paramètres de paiement (solde avant expédition, ou solde à la livraison).

### Journal des paiements (`/admin/paiements/journal`)

Tous les encaissements et remboursements (date, commande, moyen, montant, référence, motif, auteur), filtrables par type et par période, avec export CSV pour le rapprochement avec les relevés Mobile Money, bancaires et de caisse.

### Clients professionnels (`/admin/professionnels`)

Un compte professionnel se crée à partir d'un compte client existant. Le paiement sur facture n'est proposé que si un administrateur l'a **autorisé** pour ce client, avec son **plafond de crédit** (encours maximum des factures non réglées) et son **délai de paiement** ; l'autorisation est datée et tracée. Le moyen « Paiement sur facture » doit aussi être activé dans *Moyens de paiement*.

Les transitions impossibles (par exemple « livrée » sans passer par « expédiée ») sont refusées. Les **notes internes** ne sont jamais visibles du client ; le *commentaire* de changement de statut l'est. L'export CSV reprend les filtres affichés.

## Catalogue

- **Produit** : nom, **référence commerciale** (unique, distincte de l'identifiant technique ; vide = « à compléter »), **statut** (brouillon, publié, désactivé — seul « publié » est visible), catégorie ou sous-catégorie, résumé, description (mise en forme simple : ligne vide = paragraphe, `## ` titre, `- ` liste, `**gras**`), origine, utilisations, conservation, informations complémentaires, SEO, et un **point à vérifier** interne. La liste signale les produits « à vérifier » (photo à fournir, référence à compléter, point ouvert) et l'état du stock (rupture, stock faible).
- **Catégories** : une catégorie peut avoir une **catégorie parente** (un niveau de sous-catégorie) ; la catégorie principale affiche aussi les produits de ses sous-catégories.
- **Variantes** : chaque format (250 g, 500 g, 1 kg, seau…) a sa référence (SKU), son poids, son volume, son prix en FCFA entiers, son ancien prix (prix barré, promotion) et son stock. Un produit sans variante active n'apparaît pas sur le site.
- **Version anglaise** : les fiches produit, catégories, pages, questions de FAQ et zones de livraison ont un encadré *Traduction anglaise* ; chaque format a un champ *Libellé (EN)*. Un champ vide affiche le texte français sur la version anglaise du site (`/en/…`). Les textes de l'accueil se traduisent dans *Contenus → Accueil*. L'administration elle-même reste en français.
- **Photos** : photos de l'entreprise (page Facebook officielle ou photos fournies), ou photos libres de droits dont la provenance est indiquée (auteur, licence, source). Les photos libres de droits ajoutées le 14/10/2026 à la demande de l'entreprise (`php bin/import-photos.php`, liste dans `database/seeds/stock-photos.json`) se gèrent comme les autres : ordre, photo principale, suppression ; leurs crédits figurent sur /credits-photos. À chaque ajout ou remplacement : confirmation de la provenance et source (ex. « Page Facebook, publication du … »). Plusieurs photos par produit ; la première est la **photo principale** (*Définir comme principale*, flèches ↑ ↓), *Remplacer le fichier*, *Supprimer*. JPEG, PNG ou WebP, 5 Mo maximum, ré-encodées en WebP ; fichiers sur le serveur, chemin en base. Sans photo, le site affiche un visuel neutre (initiale du produit).
- **Suppression** : le produit est archivé ; les commandes passées conservent nom et prix d'origine.
- **Stock** : page *Stock* → nouvelle quantité + motif (inventaire, réception, casse). Toutes les variations sont historisées. Si une vente a lieu pendant la saisie, l'ajustement est refusé pour éviter d'écraser la vente.
- **Codes promo** : pourcentage, montant fixe ou livraison offerte ; panier minimum, dates, nombre maximal d'utilisations.

## Contenus

- **Pages et légal** : modifier le texte des pages. Cocher *Texte validé juridiquement* uniquement après relecture par un juriste ; jusque-là, un avertissement « document provisoire » est affiché.
- **Avis** : publier ou refuser. Ne jamais publier d'avis inventé ni modifier le texte d'un client.
- **Photos de l'entreprise** (`/admin/photos`) : photothèque des galeries de l'accueil et de la page À propos et de la photo de l'histoire ; provenance obligatoire ; procédure de récupération manuelle depuis Facebook rappelée sur la page.
- **Messages** (`/admin/messages`) : messages du formulaire « Contactez-nous » (nom, e-mail, téléphone facultatif, motif, objet, message), enregistrés en base. Filtres par statut (nouveau, lu, en cours de traitement, résolu, archivé) et par motif, recherche. À l'ouverture, un message nouveau passe en « lu ». **Répondre** : par e-mail depuis l'administration si l'envoi d'e-mails est configuré sur le serveur, sinon bouton *Répondre avec ma messagerie* (lien mailto vers l'adresse du visiteur). Les réponses envoyées apparaissent dans l'espace client (*Mes messages*) quand le message a été envoyé par un client connecté. Suppression définitive possible pour un message résolu ou archivé. La notification e-mail des nouveaux messages (adresse de notification des commandes) n'est annoncée active que si l'envoi d'e-mails fonctionne.
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
| Livraison et commandes | quantité maximale par article, seuil de stock faible, consignes de livraison ; liens vers *Moyens de paiement*, *Zones de livraison*, *Devises*, *Intégrations* |
| **Paramètres de paiement** | minimum de commande, délai de paiement en ligne, **acompte** (activé, pourcentage ou montant fixe, à partir de quel total, préparation après acompte, solde avant expédition ou à la livraison, délai du solde, modalités affichées), **seuil des commandes en gros** et paiement à la livraison autorisé ou non pour elles, délai par défaut et conditions du **paiement sur facture**, **conditions d'annulation et de remboursement** (affichées avant la confirmation de commande). Valeurs initiales signalées comme telles tant que l'entreprise ne les a pas validées. Montants minimum et maximum **par moyen de paiement** : dans la fiche du moyen ; frais et paiement à la livraison **par zone** : *Zones de livraison* |
| Référencement et partage | titre et description du site (FR/EN), mesure d'audience |
| Politiques et mentions légales | raison sociale, RCCM, NCC, directeur de la publication, hébergeur ; liens vers *Pages et légal* et *FAQ* |

## Moyens de paiement (`/admin/paiements`)

Pour chaque moyen : activation, affichage du logo, logo personnalisé (téléversement), nom et description (FR/EN), ordre d'affichage, et pour un paiement manuel : numéro de réception, numéro marchand, bénéficiaire, instructions (FR/EN). L'état indique honnêtement si le moyen est **proposé aux clients** :

- **paiement à la livraison** : proposé s'il est activé (et autorisé par la zone de livraison) ;
- **prestataire intégré** (Wave, Orange Money, MTN MoMo, carte via CinetPay) : proposé seulement s'il est activé **et** que ses clés sont présentes dans `.env` (les clés manquantes sont listées) ;
- **paiement manuel** (Moov Money, virement, autre moyen ajouté) : proposé seulement s'il est activé **et** qu'un numéro de réception ou des instructions sont renseignés. Le client voit les instructions après la commande et dans l'e-mail ; la commande reste *en attente de paiement* jusqu'à ce que l'équipe vérifie la réception et passe la commande à « Payée ».

Un logo n'est **jamais** affiché pour un moyen non proposé (pied de page « Paiements acceptés », panier, étape de paiement, accueil). Un moyen manuel jamais utilisé peut être supprimé ; les moyens intégrés ne peuvent être que désactivés.

## Page d'accueil (`/admin/parametres/accueil`), blocs de réassurance, textes du site

- **Page d'accueil** : texte (FR/EN) et lien des deux boutons d'action — « Découvrir la boutique » (action principale, catalogue) et « Demander un devis » (action professionnelle, formulaire de devis) ; leur affichage sur le carrousel et dans l'en-tête (écrans de 900 px et plus, où il remplace le lien « Professionnels » pour éviter un doublon ; sur mobile, la barre du bas mène à la boutique et le menu « Plus » aux professionnels) ; sections affichées sur l'accueil. Un bouton sans texte est masqué ; un lien doit être une page du site (`/…`) ou une adresse `https://`.
- **Blocs de réassurance** (`/admin/reassurance`) : les engagements affichés sous le carrousel — titre, texte, icône (liste fermée), lien facultatif, ordre, visibilité, version anglaise. N'y annoncer que des services réellement assurés.
- **Textes du site** (`/admin/textes`) : tous les libellés de l'interface publique, par rubrique (en-tête, accueil, pied de page, boutique, fiche produit, panier et commande, contact et devis, espace client), en français et en anglais. Le texte d'origine reste visible ; champ vide = texte d'origine. Les paramètres comme `:amount` doivent être conservés ; les balises sont refusées. L'inventaire complet des contenus et de leur écran d'administration est dans [INVENTAIRE-CONTENUS.md](INVENTAIRE-CONTENUS.md).
- Les mentions « Paiements acceptés » (pied de page) et les logos sous « Commander, simplement » sont **masqués** depuis le 14/10/2026 (réactivables) ; le paiement à la livraison reste proposé au moment de la commande.

## Carrousel de l'accueil (`/admin/carrousel`)

Diapositives affichées directement sous l'en-tête, texte centré sur l'image (voile dégradé pour la lisibilité), défilement automatique en fondu, flèches précédent / suivant et indicateurs ; pas de bouton lecture / pause (le défilement s'arrête pendant le survol, le focus clavier et le toucher, et n'a pas lieu si le visiteur demande de réduire les animations). Le bouton propre à chaque diapositive s'affiche en lien sous les deux boutons d'action. Contenu : image (1200 × 500 minimum) et image mobile facultative (600 × 700 minimum), texte alternatif, titre, texte, bouton et lien (page du site `/…` ou adresse `https://`), ordre (flèches ↑ ↓), activation, période de publication facultative. *Aperçu (toutes)* montre aussi les diapositives désactivées. Réglages : défilement automatique et durée (4 à 15 s). Sans diapositive publiée, l'accueil affiche son image d'ouverture. N'annoncer que des offres réellement disponibles.

## Devises d'affichage (`/admin/devises`)

Les prix et le paiement restent **en francs CFA**. Une devise activée (euro, dollar…) affiche seulement un **équivalent indicatif** à côté du prix (fiche produit, panier), avec le taux saisi par l'équipe ; une devise sans taux ne peut pas être activée. Pas de conversion automatique selon la localisation : l'euro a une parité fixe (655,957 FCFA), les autres taux sont à mettre à jour régulièrement. Un client à l'étranger ne peut commander que si une zone de livraison couvre son adresse.

## Zones de livraison

Frais, seuil de gratuité, délai, paiement à la livraison autorisé, retrait.

## Intégrations

La page *Intégrations* indique honnêtement ce qui fonctionne et ce qui reste à configurer, et présente la **liste de contrôle avant mise en production**.
