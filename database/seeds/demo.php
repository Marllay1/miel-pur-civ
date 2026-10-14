<?php
declare(strict_types=1);

/**
 * Catalogue de DÉMONSTRATION. Produits, prix, stocks et illustrations fictifs, marqués is_demo = 1.
 * Aucun avis client n'est créé : les avis affichés doivent être réels et modérés.
 */

use App\Core\DB;

return static function (): void {
    if ((int) DB::value('SELECT COUNT(*) FROM products') > 0) {
        return;
    }
    $now = gmdate('Y-m-d H:i:s');
    $cats = [
        'miels' => ['Miels', 'Miels liquides et crémeux. [À CONFIRMER : variétés réellement proposées]', 1],
        'cire-d-abeille' => ['Cire d\'abeille', 'Cire d\'abeille brute ou en pains, pour la cosmétique et l\'artisanat. [À CONFIRMER]', 2],
        'coffrets' => ['Coffrets', 'Assortiments à offrir. [À CONFIRMER]', 3],
    ];
    $catIds = [];
    foreach ($cats as $slug => [$name, $desc, $order]) {
        $catIds[$slug] = DB::insert('categories', ['parent_id' => null, 'name' => $name, 'slug' => $slug, 'description' => $desc, 'sort_order' => $order, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    }

    $products = [
        [
            'miels', 'Miel liquide toutes fleurs', 'miel-liquide-toutes-fleurs', 1, 'jar-amber.svg',
            'Miel liquide récolté dans la région de Katiola. [Produit de démonstration]',
            "Miel liquide issu des ruchers de la région de Katiola.\n\n[À COMPLÉTER : flore dominante, période de récolte, caractéristiques gustatives — à fournir par le producteur.]",
            'Région de Katiola, Côte d\'Ivoire. [À CONFIRMER : localisation précise des ruchers]',
            "- Au petit-déjeuner, sur du pain ou dans une bouillie\n- Pour sucrer thé, infusions et jus\n- En pâtisserie et en cuisine",
            [['250 g', 'pot-verre', 250, 'MPCI-LIQ-250', 2500, 40], ['500 g', 'pot-verre', 500, 'MPCI-LIQ-500', 4500, 30], ['1 kg', 'pot-plastique', 1000, 'MPCI-LIQ-1000', 8000, 15]],
        ],
        [
            'miels', 'Miel crémeux', 'miel-cremeux', 1, 'jar-cream.svg',
            'Miel à texture onctueuse, naturellement cristallisé. [Produit de démonstration]',
            "Miel à la texture fine et tartinable.\n\n[À COMPLÉTER : procédé (cristallisation naturelle ou dirigée), origine florale.]",
            'Région de Katiola. [À CONFIRMER]',
            "- À tartiner\n- Dans les yaourts et desserts",
            [['250 g', 'pot-verre', 250, 'MPCI-CRE-250', 3000, 20], ['500 g', 'pot-verre', 500, 'MPCI-CRE-500', 5500, 3]],
        ],
        [
            'miels', 'Miel en vrac pour professionnels', 'miel-vrac-professionnels', 0, 'bucket.svg',
            'Conditionnement en seau pour la restauration et la pâtisserie. [Produit de démonstration]',
            "Format adapté aux hôtels, restaurants, pâtisseries et transformateurs.\n\nPour des volumes réguliers, [demandez un devis](/commandes-professionnelles).",
            'Région de Katiola. [À CONFIRMER]',
            "- Pâtisserie, boulangerie\n- Boissons, sauces, marinades",
            [['5 kg', 'bidon', 5000, 'MPCI-VRAC-5', 37500, 6], ['25 kg', 'bidon', 25000, 'MPCI-VRAC-25', 175000, 0]],
        ],
        [
            'cire-d-abeille', 'Pain de cire d\'abeille', 'pain-de-cire-d-abeille', 0, 'wax.svg',
            'Cire d\'abeille filtrée en pain. [Produit de démonstration]',
            "Cire d'abeille issue de nos ruchers, fondue et filtrée.\n\n[À COMPLÉTER : couleur, degré de filtration, usages recommandés.]",
            'Ruchers de la région de Katiola. [À CONFIRMER]',
            "- Cosmétique artisanale (baumes, savons)\n- Bougies\n- Entretien du bois",
            [['250 g', 'pain-cire', 250, 'MPCI-CIRE-250', 3500, 25], ['1 kg', 'pain-cire', 1000, 'MPCI-CIRE-1000', 12000, 8]],
        ],
        [
            'coffrets', 'Coffret découverte', 'coffret-decouverte', 1, 'box.svg',
            'Un miel liquide, un miel crémeux et un petit pain de cire. [Produit de démonstration]',
            "Assortiment à offrir.\n\n[À CONFIRMER : composition exacte et présentation du coffret.]",
            'Région de Katiola. [À CONFIRMER]',
            "- Cadeau d'entreprise\n- Fêtes de fin d'année",
            [['Coffret 3 produits', 'coffret', 800, 'MPCI-COF-3', 9500, 10]],
        ],
    ];
    foreach ($products as [$cat, $name, $slug, $featured, $image, $short, $desc, $origin, $usage, $variants]) {
        $pid = DB::insert('products', [
            'category_id' => $catIds[$cat], 'name' => $name, 'slug' => $slug, 'short_description' => $short,
            'description' => $desc, 'origin' => $origin, 'usage_tips' => $usage,
            'storage_tips' => "Conserver à température ambiante, à l'abri de l'humidité et de la lumière. [À CONFIRMER]",
            'is_active' => 1, 'is_featured' => $featured, 'is_demo' => 1, 'meta_title' => null, 'meta_description' => null,
            'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
        DB::insert('product_images', ['product_id' => $pid, 'path' => '/assets/img/demo/' . $image, 'alt' => $name . ' (illustration de démonstration)', 'sort_order' => 0, 'is_demo' => 1, 'created_at' => $now]);
        foreach ($variants as $i => [$label, $format, $grams, $sku, $price, $stock]) {
            $vid = DB::insert('product_variants', [
                'product_id' => $pid, 'label' => $label, 'format' => $format, 'weight_grams' => $grams, 'sku' => $sku,
                'price' => $price, 'compare_at_price' => null, 'stock' => $stock, 'is_active' => 1, 'sort_order' => $i,
                'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ]);
            if ($stock > 0) {
                DB::insert('stock_movements', ['variant_id' => $vid, 'quantity' => $stock, 'reason' => 'initial', 'reference' => 'DEMO', 'user_id' => null, 'note' => 'Stock de démonstration', 'created_at' => $now]);
            }
        }
    }

    DB::insert('promo_codes', [
        'code' => 'DEMO10', 'description' => 'Code de démonstration : -10 % dès 5 000 FCFA', 'type' => 'percent', 'value' => 10,
        'min_subtotal' => 5000, 'starts_at' => null, 'ends_at' => null, 'max_uses' => 100, 'used_count' => 0, 'is_active' => 1,
        'created_at' => $now, 'updated_at' => $now,
    ]);
};
