# Identité visuelle : logo, favicon et photographies

## 1. Logo officiel (installé le 14/10/2026)

Source : planche de marque fournie par la boutique (logo, favicon, palette, version monochrome). Les déclinaisons ont été extraites sans modification des formes ni des couleurs : fond ivoire rendu transparent, contours adoucis.

| Fichier (`public/assets/img/brand/`) | Usage |
|---|---|
| `logo.png` | En-tête du site, à gauche, lien vers l'accueil : emblème à gauche du nom (disposition horizontale adaptée à la hauteur de l'en-tête, proportions conservées) |
| `logo-dark.png` | Même logo, nom en ivoire, affiché automatiquement en thème sombre (une seule version visible à la fois) |
| `logo-stacked.png` / `logo-stacked-dark.png` | Logo complet de la planche (emblème au-dessus du nom) : pied de page, pages de connexion client et administration |
| `emblem.png` | Symbole seul (goutte, abeille, alvéoles, feuilles) : source des favicons |
| `favicon.ico` (16/32/48), `favicon-16.png`, `favicon-32.png`, `apple-touch-icon.png` (180, fond blanc), `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`, `site.webmanifest` | Onglet du navigateur, favoris, écran d'accueil iOS/Android ; copie de `favicon.ico` à la racine publique |

Palette relevée sur la planche et appliquée au site : vert nature `#184626` (boutons, liens, titres), or miel `#E9A50A` (accents), orange `#EC7110`, ivoire `#F4EEDB` (fonds).

La disposition horizontale de l'en-tête est une composition des éléments du logo officiel (emblème + nom) : si la boutique dispose d'une version horizontale officielle, la fournir pour la remplacer.

### Remplacer le logo

Le plus simple : **Administration → Paramètres du site → Logos et images** (téléversement avec aperçu, contrôle des dimensions, retour possible au logo installé). En ligne de commande, pour remplacer les fichiers installés :

```bash
php bin/brand.php --logo=logo-horizontal.png --logo-dark=logo-horizontal-clair.png --logo-stacked=logo.png --logo-stacked-dark=logo-clair.png --icon=embleme.png
```

PNG à fond transparent. Seul `--logo` est obligatoire ; sans `--icon`, les favicons sont faits à partir du logo complet (déconseillé : texte illisible en 16 px). `php bin/brand.php --remove-logo` revient au nom en toutes lettres. Après remplacement : rechargement sans cache (Ctrl + F5).

## Logos des moyens de paiement

Dans `public/assets/img/payments/`, affichés sur fond neutre, sans déformation, uniquement pour les moyens réellement proposés :

| Logo | Fichier | Source |
|---|---|---|
| Visa | `visa.svg` | Wikimedia Commons, « Visa Inc. logo (2021–present).svg » (domaine public, marque déposée de Visa) |
| Mastercard | `mastercard.svg` | Wikimedia Commons, « Mastercard 2019 logo.svg » (symbole seul ; marque déposée de Mastercard) |
| Orange Money | `orange-money.svg` | Wikimedia Commons, « Orange Money logo » (marque déposée d'Orange) |
| MTN MoMo | `mtn-momo.png` | logo officiel du portail développeurs MTN MoMo (momodeveloper.mtn.com), réduit à 240 px |
| Wave | `wave.png` | logo du site officiel wave.com |
| Moov Money | — | aucun logo officiel téléchargeable trouvé : le nom s'affiche en texte. Téléverser le logo fourni par Moov Africa dans *Moyens de paiement* |

Ces marques appartiennent à leurs titulaires ; vérifier auprès de chaque prestataire, à la signature du contrat, ses règles d'usage du logo.

## Réseaux sociaux

Logos officiels de Facebook et WhatsApp (ainsi qu'Instagram et TikTok si ces comptes sont renseignés), tracés de la bibliothèque [Simple Icons](https://simpleicons.org) 11.14.0 (licence CC0), couleurs de marque officielles, dans `app/config/social-icons.php`. YouTube et LinkedIn sont aussi disponibles. Un réseau n'apparaît que si son adresse est renseignée **et** son affichage coché dans *Administration → Paramètres du site → Réseaux sociaux* (WhatsApp : *Coordonnées*) : aucun lien n'est inventé. Chaque icône a un libellé pour les lecteurs d'écran.

## 2. Photographies

Toutes les photos du site sont décrites dans `app/config/photos.php` (fichier, dimensions, texte alternatif FR/EN, origine, crédit). Deux origines, toujours indiquées sous l'image :

| Origine | Usage | Mention affichée |
|---|---|---|
| **Photos de l'entreprise** : publiées sur la page Facebook officielle (consultée le 14/10/2026) | Accueil (galerie « Nos produits en images »), catégorie Miels, Coffrets, fiche Miel liquide, Notre histoire, Professionnels | « Photo : Miel Pur de Côte d'Ivoire » |
| **Photos d'illustration** : Wikimedia Commons, licences libres | Apiculture (Notre démarche), cadre de rayons, miel crémeux, cire, vrac | « Photo d'illustration — auteur, Wikimedia Commons, licence » + badge « Illustration » sur les vignettes |

La page **/credits-photos** (lien dans le pied de page) liste l'ensemble avec auteurs, licences et liens vers les sources.

### Photos d'illustration utilisées

| Fichier | Sujet | Auteur | Licence | Source |
|---|---|---|---|---|
| `wm-pot-miel-rayon.webp` | Pot de miel avec rayon et cuillère (photo d'ouverture de l'accueil) | Alabama Extension | CC0 1.0 (domaine public) | https://commons.wikimedia.org/wiki/File:Small_Honey_Jar_with_Honeycomb.jpg |
| `wm-apiculteurs-cameroun.webp` | Apiculteurs autour d'une ruche à barrettes (Cameroun) | Usman H Dukku | CC BY-SA 4.0 | https://commons.wikimedia.org/wiki/File:Woman-Beekeeper-Cameroon.jpg |
| `wm-cadre-rayons.webp` | Cadre de ruche couvert d'abeilles | Øyvind Holmstad | CC BY-SA 4.0 | https://commons.wikimedia.org/wiki/File:Honey_comb_frame_2017.jpg |
| `wm-pains-cire.webp` | Pains de cire d'abeille | Mauro Cateb | CC BY-SA 3.0 | https://commons.wikimedia.org/wiki/File:Beeswax_blocks.JPG |
| `wm-miel-cremeux.webp` | Miel crémeux et miel liquide en pots | Zaereth | CC BY-SA 4.0 | https://commons.wikimedia.org/wiki/File:Creamed_honey_Maillard_reaction_after_aging.jpg |
| `wm-filet-miel.webp` | Filet de miel | Brandy Frisky | CC BY-SA 3.0 | https://commons.wikimedia.org/wiki/File:Honey_Fall.jpg |

Conditions : ces licences autorisent l'usage commercial à condition de **citer l'auteur et la licence** (fait sous chaque image et sur /credits-photos) et de diffuser les **versions modifiées** (ici redimensionnées et converties en WebP) sous la même licence (indiqué sur /credits-photos). Elles ne représentent ni les ruchers ni les produits de l'entreprise et ne sont jamais présentées comme telles.

### Photos de l'entreprise

`fb-miel-en-rayon-vente.webp` (miel en rayon « Miel de l'apiculteur — En vente ici »), `fb-bouteilles-trio.webp`, `fb-bouteille-fond-jaune.webp`, `fb-bouteilles-duo.webp`, `fb-stand-coffrets.webp`. Elles proviennent des images publiquement visibles de la page Facebook ; les versions accessibles sans connexion sont de taille modeste (266 à 960 px). **À fournir par l'entreprise** : les fichiers originaux en haute définition (et la confirmation qu'elle en détient les droits, notamment pour les photos de studio), ainsi que des photos des ruchers de Katiola, de la récolte, du miel crémeux, de la cire et des seaux de vrac, pour remplacer les photos d'illustration.

### Remplacer une photo

- **Photos de produits** : Administration → Produits → fiche → *Images* (JPEG, PNG ou WebP, ré-encodées automatiquement) ; cocher *Image principale* puis supprimer l'ancienne.
- **Photos des pages** (accueil, Notre histoire, Professionnels) : déposer le fichier WebP dans `public/assets/img/photos/` et mettre à jour son entrée dans `app/config/photos.php` (dimensions, texte alternatif, origine ; pour une photo de l'entreprise : `'origin' => 'company'`).
