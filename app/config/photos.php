<?php
declare(strict_types=1);

/**
 * Registre des photographies du site (public/assets/img/photos/).
 *  - origin « company » : photo publiée par l'entreprise sur sa page Facebook officielle
 *    (https://www.facebook.com/p/Le-Miel-Pur-de-C%C3%B4te-dIvoire-100062970108746/), consultée le 14/10/2026 ;
 * Seules les photographies de l'entreprise sont utilisées (les photos d'illustration libres de droits ont été retirées le 14/10/2026).
 * Les fichiers ont été redimensionnés et convertis en WebP. Les nouvelles photos s'ajoutent depuis l'administration
 * (Photos de l'entreprise, fiches produits, carrousel).
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
];
