<?php
declare(strict_types=1);

/**
 * Registre des photographies du site (public/assets/img/photos/).
 *  - origin « company » : photo publiée par l'entreprise sur sa page Facebook officielle
 *    (https://www.facebook.com/p/Le-Miel-Pur-de-C%C3%B4te-dIvoire-100062970108746/), consultée le 14/10/2026 ;
 *  - origin « illustration » : photo libre de droits (Wikimedia Commons) ; elle ne représente ni les ruchers ni les produits
 *    de l'entreprise et est toujours légendée « Photo d'illustration » avec son crédit.
 * Les fichiers ont été redimensionnés et convertis en WebP ; pour les photos sous licence CC BY-SA,
 * ces versions adaptées sont diffusées sous la même licence.
 */
return [
    'fb-miel-en-rayon-vente' => [
        'file' => 'fb-miel-en-rayon-vente.webp', 'w' => 960, 'h' => 521, 'origin' => 'company',
        'alt' => ['fr' => 'Barquettes de miel en rayon empilées à côté d\'un panneau « Miel de l\'apiculteur — En vente ici »',
                  'en' => 'Stacked tubs of comb honey next to a "Beekeeper\'s honey — On sale here" sign'],
    ],
    'fb-bouteilles-trio' => [
        'file' => 'fb-bouteilles-trio.webp', 'w' => 640, 'h' => 640, 'origin' => 'company',
        'alt' => ['fr' => 'Trois bouteilles de Miel Pur de Côte d\'Ivoire, étiquettes avant et arrière',
                  'en' => 'Three bottles of Miel Pur de Côte d\'Ivoire honey, front and back labels'],
    ],
    'fb-bouteille-fond-jaune' => [
        'file' => 'fb-bouteille-fond-jaune.webp', 'w' => 393, 'h' => 590, 'origin' => 'company',
        'alt' => ['fr' => 'Bouteille de Miel Pur de Côte d\'Ivoire tenue en main sur fond jaune',
                  'en' => 'Hand holding a bottle of Miel Pur de Côte d\'Ivoire honey against a yellow background'],
    ],
    'fb-bouteilles-duo' => [
        'file' => 'fb-bouteilles-duo.webp', 'w' => 266, 'h' => 590, 'origin' => 'company',
        'alt' => ['fr' => 'Deux bouteilles de miel ambré Miel Pur de Côte d\'Ivoire',
                  'en' => 'Two bottles of amber Miel Pur de Côte d\'Ivoire honey'],
    ],
    'fb-stand-coffrets' => [
        'file' => 'fb-stand-coffrets.webp', 'w' => 590, 'h' => 266, 'origin' => 'company',
        'alt' => ['fr' => 'Stand de vente : bouteilles de miel alignées et paniers cadeaux emballés',
                  'en' => 'Sales stand: rows of honey bottles and wrapped gift baskets'],
    ],
    'wm-apiculteurs-cameroun' => [
        'file' => 'wm-apiculteurs-cameroun.webp', 'small' => 'wm-apiculteurs-cameroun-800.webp', 'w' => 1600, 'h' => 1060, 'origin' => 'illustration',
        'alt' => ['fr' => 'Apiculteurs et apicultrices réunis autour d\'une ruche à barrettes, au Cameroun',
                  'en' => 'Beekeepers gathered around a top-bar hive in Cameroon'],
        'author' => 'Usman H Dukku', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
        'source' => 'https://commons.wikimedia.org/wiki/File:Woman-Beekeeper-Cameroon.jpg',
    ],
    'wm-cadre-rayons' => [
        'file' => 'wm-cadre-rayons.webp', 'small' => 'wm-cadre-rayons-800.webp', 'w' => 1600, 'h' => 1067, 'origin' => 'illustration',
        'alt' => ['fr' => 'Cadre de ruche couvert d\'abeilles, tenu par un apiculteur',
                  'en' => 'Beekeeper holding a hive frame covered with bees'],
        'author' => 'Øyvind Holmstad', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
        'source' => 'https://commons.wikimedia.org/wiki/File:Honey_comb_frame_2017.jpg',
    ],
    'wm-pains-cire' => [
        'file' => 'wm-pains-cire.webp', 'w' => 1000, 'h' => 749, 'origin' => 'illustration',
        'alt' => ['fr' => 'Deux pains de cire d\'abeille', 'en' => 'Two blocks of beeswax'],
        'author' => 'Mauro Cateb', 'license' => 'CC BY-SA 3.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0/',
        'source' => 'https://commons.wikimedia.org/wiki/File:Beeswax_blocks.JPG',
    ],
    'wm-miel-cremeux' => [
        'file' => 'wm-miel-cremeux.webp', 'w' => 1000, 'h' => 665, 'origin' => 'illustration',
        'alt' => ['fr' => 'Pot de miel crémeux à côté d\'un pot de miel liquide', 'en' => 'Jar of creamed honey next to a jar of liquid honey'],
        'author' => 'Zaereth', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
        'source' => 'https://commons.wikimedia.org/wiki/File:Creamed_honey_Maillard_reaction_after_aging.jpg',
    ],
    'wm-filet-miel' => [
        'file' => 'wm-filet-miel.webp', 'w' => 960, 'h' => 1280, 'origin' => 'illustration',
        'alt' => ['fr' => 'Filet de miel qui s\'écoule', 'en' => 'Stream of honey pouring'],
        'author' => 'Brandy Frisky', 'license' => 'CC BY-SA 3.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0/',
        'source' => 'https://commons.wikimedia.org/wiki/File:Honey_Fall.jpg',
    ],
];
