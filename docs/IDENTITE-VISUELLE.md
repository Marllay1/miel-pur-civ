# Identité visuelle : logo, favicon et photographies

## 1. Logo officiel (en attente)

Le logo officiel n'a pas encore été fourni. En attendant :

- les en-têtes (site, administration, pages de connexion) affichent le **nom en toutes lettres** (« Miel Pur — de Côte d'Ivoire ») ; l'ancien pictogramme hexagone + goutte, qui était une proposition graphique, a été retiré ;
- les icônes d'onglet et d'écran d'accueil sont **provisoires** : un monogramme « M » neutre sur fond miel (fichier témoin `public/assets/img/brand/PROVISOIRE.txt`) ;
- aucun logo n'est déclaré aux moteurs de recherche (données structurées).

### Installer le logo dès réception

Fournir de préférence :

1. le **logo complet** en PNG à fond transparent (au moins 800 px de large) ou en SVG ;
2. une **version carrée** (emblème sans texte, ou initiale) en PNG d'au moins 512 × 512 px pour les favicons : un logo comportant du texte devient illisible à 16 px.

Puis, dans le dossier du projet :

```bash
php bin/brand.php --logo=chemin/logo.png --icon=chemin/embleme.png
```

Le script :

- installe le logo dans `public/assets/img/brand/` (marges vides retirées, largeur limitée à 800 px, **proportions conservées**) ; il est affiché à hauteur fixe (40 px dans l'en-tête, 64 px sur les pages de connexion) sans jamais être déformé ;
- génère `favicon.ico` (16, 32 et 48 px), `favicon-16.png`, `favicon-32.png`, `apple-touch-icon.png` (180 px, fond opaque exigé par iOS), `icon-192.png`, `icon-512.png`, une icône « maskable » Android et `site.webmanifest`, ainsi qu'une copie de `favicon.ico` à la racine publique ;
- supprime les icônes provisoires.

Options : `--background=#RRGGBB` (fond de l'icône iOS, blanc par défaut). Sans `--icon`, les favicons sont faits à partir du logo complet. `php bin/brand.php --remove-logo` revient au nom en toutes lettres.

Vérification : recharger le site sans cache (Ctrl + F5) et contrôler l'onglet ; sur téléphone, « Ajouter à l'écran d'accueil ». Les navigateurs gardent parfois l'ancienne icône en cache plusieurs heures.

## 2. Photographies

Toutes les photos du site sont décrites dans `app/config/photos.php` (fichier, dimensions, texte alternatif FR/EN, origine, crédit). Deux origines, toujours indiquées sous l'image :

| Origine | Usage | Mention affichée |
|---|---|---|
| **Photos de l'entreprise** : publiées sur la page Facebook officielle (consultée le 14/10/2026) | Accueil (visuel d'ouverture, galerie), catégorie Miels, Coffrets, fiche Miel liquide, Notre histoire, Professionnels | « Photo : Miel Pur de Côte d'Ivoire » |
| **Photos d'illustration** : Wikimedia Commons, licences libres | Apiculture (Notre démarche), cadre de rayons, miel crémeux, cire, vrac | « Photo d'illustration — auteur, Wikimedia Commons, licence » + badge « Illustration » sur les vignettes |

La page **/credits-photos** (lien dans le pied de page) liste l'ensemble avec auteurs, licences et liens vers les sources.

### Photos d'illustration utilisées

| Fichier | Sujet | Auteur | Licence | Source |
|---|---|---|---|---|
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
