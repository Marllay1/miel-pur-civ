# Inventaire des contenus du site public et de leur administration

Établi le 14/10/2026, page par page. Pour chaque élément : où il apparaît, d'où il vient, où le modifier.
« Textes du site » = Administration → Site public → **Textes du site** (`/admin/textes`) : tout libellé de l'interface publique (français et anglais), le texte d'origine restant affiché en gris ; un champ vide rétablit le texte d'origine.

## En-tête et navigation (toutes les pages)

| Élément | Source | Où le modifier |
|---|---|---|
| Logo (clair / sombre), favicon | `settings` (images téléversées), à défaut logo installé | Paramètres du site → Logos et images |
| Nom commercial, slogan | `settings` | Paramètres du site → Identité |
| Libellés du menu (Boutique, Notre histoire, Professionnels, FAQ, Contact), barre mobile, menu « Plus » | textes d'interface | Textes du site → En-tête et navigation |
| Bouton « Demander un devis » (écrans ≥ 900 px) | `settings` (texte FR/EN, lien, affichage) | Paramètres du site → **Page d'accueil** |
| Recherche, thème, langues, réseaux dans l'en-tête | `settings` | Paramètres du site → Apparence / Langues / Réseaux sociaux |

## Accueil

| Élément | Source | Où le modifier |
|---|---|---|
| Carrousel : image (ordinateur, mobile), texte alternatif, titre, texte, bouton propre, lien, ordre, activation, période, provenance | table `home_slides` | Carrousel de l'accueil |
| Boutons du carrousel « Découvrir la boutique » / « Demander un devis » (texte FR/EN, lien, affichage) | `settings` | Paramètres du site → Page d'accueil |
| Défilement automatique, durée | `settings` | Carrousel de l'accueil → Réglages |
| Image et textes d'ouverture (sans diapositive active) | `settings` | Logos et images ; Textes de l'accueil |
| **Blocs de réassurance** (titre, texte, icône, lien, ordre, visibilité, FR/EN) | table `home_highlights` | **Blocs de réassurance** |
| Sections affichées (produits, catégories, démarche, galerie, « Commander, simplement », professionnels, FAQ) | `settings` | Paramètres du site → Page d'accueil |
| Titres et sous-titres des sections, étapes « Commander, simplement », libellés « Tout voir »… | textes d'interface | Textes du site → Page d'accueil |
| Texte « Notre démarche », texte « Professionnels », titre et introduction d'ouverture | `settings` | Textes de l'accueil |
| Produits mis en avant | `products.is_featured` | Produits → case « À la une » |
| Vignettes de catégories | première photo d'un produit de la catégorie | Produits → Photos |
| Photo de l'histoire, galerie | table `media` | Photos de l'entreprise |
| Logos de paiement sous « Commander, simplement » | masqués (demande du 14/10/2026), réactivables | Paramètres du site → Page d'accueil |
| FAQ (aperçu) | table `faqs` | FAQ |

## Pied de page

| Élément | Source | Où le modifier |
|---|---|---|
| Logo, présentation courte | `settings` | Logos et images ; Identité |
| Coordonnées (téléphones, WhatsApp, e-mails, adresse, carte) | `settings` | Coordonnées et contact |
| Réseaux sociaux | `settings` | Réseaux sociaux |
| Titres des colonnes, libellés des liens | textes d'interface | Textes du site → Pied de page |
| Lettre d'information | `settings` (affichage) | Apparence |
| « Paiements acceptés » | **masqué** (demande du 14/10/2026), réactivable | Apparence |

## Boutique, fiche produit, panier, commande

| Élément | Source | Où le modifier |
|---|---|---|
| Produits : nom, référence, statut, descriptions, informations, SEO, à la une | table `products` (+ traductions) | Produits |
| Formats : libellé, référence, prix, ancien prix (promotion), poids, volume, stock | table `product_variants` | Produits → Variantes ; Stock |
| Photos, ordre, photo principale, texte alternatif, provenance | table `product_images` | Produits → Photos |
| Catégories, sous-catégories | table `categories` | Catégories |
| Badges (« Coup de cœur »), messages de stock (« En stock », « Plus que … »), libellés de tri et filtres | textes d'interface | Textes du site → Boutique / Fiche produit |
| Codes promo | table `promo_codes` | Codes promo |
| Zones, frais et délais de livraison | table `shipping_zones` | Zones de livraison |
| Moyens de paiement réellement proposés, instructions | table `payment_methods` | Moyens de paiement |
| Acompte, facture, conditions d'annulation affichées avant la commande | `settings` | Paramètres de paiement |
| Libellés du tunnel de commande, page de commande | textes d'interface | Textes du site → Panier, commande et suivi |
| Devises indicatives | table `currencies` | Devises |

## Pages et formulaires

| Élément | Source | Où le modifier |
|---|---|---|
| Notre histoire, CGV, confidentialité, livraison, retours, mentions légales | table `pages` (+ traductions) | Pages et légal |
| Galerie « À propos » | table `media` | Photos de l'entreprise |
| FAQ | table `faqs` | FAQ |
| Contact : textes, motifs | textes d'interface | Textes du site → Contact, devis et FAQ |
| Demande de devis : textes, activités | textes d'interface | Textes du site → Contact, devis et FAQ |
| Bannière de la page Professionnels | photo du stand (registre `app/config/photos.php`) | dans le code (voir ci-dessous) |

## Référencement et partage

| Élément | Source | Où le modifier |
|---|---|---|
| Titre et description du site, image de partage | `settings` | Référencement ; Logos et images |
| Titre et description SEO d'un produit | `products.meta_*` | Produits |
| Description d'une page | `pages.meta_description` | Pages et légal |
| Textes alternatifs | photos des produits, diapositives, photothèque | écrans correspondants |

## Ce qui reste volontairement dans le code

| Élément | Raison |
|---|---|
| Structure des pages et des composants, mise en page, grilles, points de rupture | cohérence visuelle et accessibilité ; modifier la structure demande des tests |
| Navigation (ordre des liens, barre mobile, fil d'Ariane), comportement du carrousel (fondu, clavier, glissement, pauses) | mécanismes de navigation et d'accessibilité |
| Couleurs et polices au-delà des choix proposés (pas de CSS libre) | lisibilité : les contrastes sont vérifiés |
| Authentification, autorisations, sécurité (CSRF, en-têtes, limites de fréquence) | sécurité |
| Validation des formulaires, calculs (prix, remises, frais, acompte, état du paiement), statuts de commande | règles métier sensibles, calculées par le serveur |
| E-mails, messages d'erreur et de validation, statuts techniques | messages système ; reformulation possible plus tard si besoin |
| Liste des icônes proposées pour les blocs de réassurance | jeu d'icônes du site (aucune image externe) |
| Bannière de la page Professionnels | seule image de page encore fixée : photo du stand de l'entreprise |
| Valeurs par défaut à l'installation (`app/config/defaults.php`, `app/lang/*.php`, migrations) | remplacées dès qu'une valeur est enregistrée en administration |
