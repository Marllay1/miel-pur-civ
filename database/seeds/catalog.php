<?php
declare(strict_types=1);

/**
 * Catalogue initial (gamme, formats et prix confirmés par le client le 2026-10-14), en français et en anglais.
 * Les stocks sont des valeurs de départ à ajuster dans l'administration (page Stock).
 * Aucun avis client n'est créé : seuls des avis réels, modérés, sont publiés.
 */

use App\Core\DB;
use App\Services\I18n;

return static function (): void {
    if ((int) DB::value('SELECT COUNT(*) FROM products') > 0) {
        return;
    }
    $now = gmdate('Y-m-d H:i:s');
    $cats = [
        'miels' => [['Miels', 'Miels liquides et crémeux de Katiola, 100 % bio.'], ['Honey', 'Liquid and creamed honey from Katiola, 100% organic.'], 1],
        'cire-d-abeille' => [['Cire d\'abeille', 'Cire d\'abeille issue de nos ruchers, pour la cosmétique, les bougies et l\'artisanat.'], ['Beeswax', 'Beeswax from our apiaries, for cosmetics, candles and crafts.'], 2],
        'coffrets' => [['Coffrets', 'Nos produits réunis dans des coffrets à offrir.'], ['Gift boxes', 'Our products gathered in gift boxes.'], 3],
    ];
    $catIds = [];
    foreach ($cats as $slug => [[$name, $desc], [$nameEn, $descEn], $order]) {
        $catIds[$slug] = DB::insert('categories', ['parent_id' => null, 'name' => $name, 'slug' => $slug, 'description' => $desc, 'sort_order' => $order, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        I18n::save('category', $catIds[$slug], ['name' => $nameEn, 'description' => $descEn]);
    }

    $storage = "Conserver à température ambiante, à l'abri de l'humidité et de la lumière, pot bien fermé. La cristallisation est naturelle : un bain-marie tiède (40 °C maximum) rend au miel sa texture liquide.";
    $storageEn = "Store at room temperature, away from moisture and light, with the lid tightly closed. Crystallisation is natural: a lukewarm water bath (40 °C maximum) makes the honey liquid again.";
    $products = [
        [
            'miels', 'miel-liquide-toutes-fleurs', 1, 'jar-amber.svg',
            ['Miel liquide toutes fleurs', 'Notre miel de Katiola, 100 % bio, issu de la diversité des floraisons du nord ivoirien.',
             "Récolté dans nos ruchers installés au cœur de la végétation naturelle du nord de la Côte d'Ivoire, ce miel toutes fleurs reflète la diversité des plantes butinées par nos abeilles.\n\n**100 % bio et 100 % miel**, sans aucun ajout. Certifié conforme aux normes internationales par un laboratoire habilité par l'INAO.",
             "Ruchers de la région de Katiola, centre-nord de la Côte d'Ivoire.",
             "- Au petit-déjeuner, sur du pain, dans une bouillie ou un yaourt\n- Pour sucrer thé, infusions et jus de fruits\n- En pâtisserie, en marinade et en sauce",
             'Illustration d\'un pot de miel liquide doré'],
            ['Multifloral liquid honey', 'Our honey from Katiola, 100% organic, born from the diversity of flowering plants in northern Côte d\'Ivoire.',
             "Harvested from our apiaries in the heart of the natural vegetation of northern Côte d'Ivoire, this multifloral honey reflects the variety of plants our bees forage.\n\n**100% organic and 100% honey**, with nothing added. Certified compliant with international standards by an INAO-approved laboratory.",
             "Apiaries in the Katiola region, north-central Côte d'Ivoire.",
             "- At breakfast, on bread, in porridge or yoghurt\n- To sweeten tea, herbal infusions and fruit juice\n- In baking, marinades and sauces",
             'Illustration of a jar of golden liquid honey'],
            [['250 g', '250 g', 'pot-verre', 250, 'MPCI-LIQ-250', 2500, 40], ['500 g', '500 g', 'pot-verre', 500, 'MPCI-LIQ-500', 4500, 30], ['1 kg', '1 kg', 'pot-plastique', 1000, 'MPCI-LIQ-1000', 8000, 15]],
        ],
        [
            'miels', 'miel-cremeux', 1, 'jar-cream.svg',
            ['Miel crémeux', 'Un miel à la texture fine et onctueuse, qui se tartine sans couler.',
             "Notre miel de Katiola, travaillé pour obtenir une cristallisation fine et régulière : une texture crémeuse, idéale à tartiner.\n\n**100 % bio et 100 % miel**, sans aucun ajout. Certifié conforme aux normes internationales par un laboratoire habilité par l'INAO.",
             "Ruchers de la région de Katiola, centre-nord de la Côte d'Ivoire.",
             "- À tartiner sur du pain ou des crêpes\n- Dans les yaourts, fromages blancs et desserts",
             'Illustration d\'un pot de miel crémeux'],
            ['Creamed honey', 'Honey with a fine, velvety texture that spreads without dripping.',
             "Our honey from Katiola, worked to obtain a fine, even crystallisation: a creamy texture that is perfect for spreading.\n\n**100% organic and 100% honey**, with nothing added. Certified compliant with international standards by an INAO-approved laboratory.",
             "Apiaries in the Katiola region, north-central Côte d'Ivoire.",
             "- Spread on bread or pancakes\n- In yoghurt, fromage frais and desserts",
             'Illustration of a jar of creamed honey'],
            [['250 g', '250 g', 'pot-verre', 250, 'MPCI-CRE-250', 3000, 20], ['500 g', '500 g', 'pot-verre', 500, 'MPCI-CRE-500', 5500, 3]],
        ],
        [
            'miels', 'miel-vrac-professionnels', 0, 'bucket.svg',
            ['Miel en vrac pour professionnels', 'Notre miel en seau, pour la restauration, la pâtisserie et la transformation.',
             "Le même miel 100 % bio de Katiola, conditionné en grands formats pour les hôtels, restaurants, pâtisseries, boulangeries et artisans.\n\nPour des volumes réguliers, [demandez un devis](/commandes-professionnelles).",
             "Ruchers de la région de Katiola, centre-nord de la Côte d'Ivoire.",
             "- Pâtisserie, viennoiserie, boulangerie\n- Boissons, sauces, marinades\n- Cosmétique et savonnerie artisanales",
             'Illustration d\'un seau de miel'],
            ['Bulk honey for businesses', 'Our honey in buckets, for catering, baking and food processing.',
             "The same 100% organic honey from Katiola, packed in large sizes for hotels, restaurants, bakeries and artisans.\n\nFor regular volumes, [request a quote](/commandes-professionnelles).",
             "Apiaries in the Katiola region, north-central Côte d'Ivoire.",
             "- Pastry, baking and bread making\n- Drinks, sauces, marinades\n- Artisanal cosmetics and soap making",
             'Illustration of a bucket of honey'],
            [['5 kg', '5 kg', 'bidon', 5000, 'MPCI-VRAC-5', 37500, 6], ['25 kg', '25 kg', 'bidon', 25000, 'MPCI-VRAC-25', 175000, 0]],
        ],
        [
            'cire-d-abeille', 'pain-de-cire-d-abeille', 0, 'wax.svg',
            ['Pain de cire d\'abeille', 'Cire d\'abeille de nos ruchers, fondue et filtrée.',
             "La cire produite par nos abeilles, récupérée lors de la récolte puis fondue et filtrée pour être moulée en pains.\n\nUne matière première naturelle pour vos créations.",
             "Ruchers de la région de Katiola, centre-nord de la Côte d'Ivoire.",
             "- Cosmétique artisanale : baumes, crèmes, savons\n- Bougies\n- Entretien et protection du bois",
             'Illustration d\'un pain de cire d\'abeille'],
            ['Beeswax block', 'Beeswax from our apiaries, melted and filtered.',
             "The wax produced by our bees, collected during the harvest, then melted and filtered before being moulded into blocks.\n\nA natural raw material for your creations.",
             "Apiaries in the Katiola region, north-central Côte d'Ivoire.",
             "- Artisanal cosmetics: balms, creams, soaps\n- Candles\n- Wood care and protection",
             'Illustration of a beeswax block'],
            [['250 g', '250 g', 'pain-cire', 250, 'MPCI-CIRE-250', 3500, 25], ['1 kg', '1 kg', 'pain-cire', 1000, 'MPCI-CIRE-1000', 12000, 8]],
        ],
        [
            'coffrets', 'coffret-decouverte', 1, 'box.svg',
            ['Coffret découverte', 'Un miel liquide, un miel crémeux et un pain de cire, réunis dans un coffret.',
             "Le meilleur de nos ruchers dans un coffret à offrir : notre miel liquide toutes fleurs, notre miel crémeux et un pain de cire d'abeille.",
             "Ruchers de la région de Katiola, centre-nord de la Côte d'Ivoire.",
             "- Cadeau d'entreprise\n- Fêtes et anniversaires\n- Découverte de nos produits",
             'Illustration d\'un coffret cadeau'],
            ['Discovery gift box', 'A liquid honey, a creamed honey and a beeswax block, together in a gift box.',
             "The best of our apiaries in a gift box: our multifloral liquid honey, our creamed honey and a beeswax block.",
             "Apiaries in the Katiola region, north-central Côte d'Ivoire.",
             "- Corporate gifts\n- Celebrations and birthdays\n- Discovering our products",
             'Illustration of a gift box'],
            [['Coffret 3 produits', '3-product box', 'coffret', 800, 'MPCI-COF-3', 9500, 10]],
        ],
    ];
    foreach ($products as [$cat, $slug, $featured, $image, $fr, $en, $variants]) {
        [$name, $short, $desc, $origin, $usage, $alt] = $fr;
        [$nameEn, $shortEn, $descEn, $originEn, $usageEn, $altEn] = $en;
        $pid = DB::insert('products', [
            'category_id' => $catIds[$cat], 'name' => $name, 'slug' => $slug, 'short_description' => $short,
            'description' => $desc, 'origin' => $origin, 'usage_tips' => $usage, 'storage_tips' => $storage,
            'is_active' => 1, 'is_featured' => $featured, 'is_demo' => 0, 'meta_title' => null, 'meta_description' => null,
            'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
        I18n::save('product', $pid, ['name' => $nameEn, 'short_description' => $shortEn, 'description' => $descEn, 'origin' => $originEn, 'usage_tips' => $usageEn, 'storage_tips' => $storageEn]);
        DB::insert('product_images', ['product_id' => $pid, 'path' => '/assets/img/products/' . $image, 'alt' => $alt, 'sort_order' => 0, 'is_demo' => 0, 'created_at' => $now]);
        foreach ($variants as $i => [$label, $labelEn, $format, $grams, $sku, $price, $stock]) {
            $vid = DB::insert('product_variants', [
                'product_id' => $pid, 'label' => $label, 'format' => $format, 'weight_grams' => $grams, 'sku' => $sku,
                'price' => $price, 'compare_at_price' => null, 'stock' => $stock, 'is_active' => 1, 'sort_order' => $i,
                'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ]);
            if ($labelEn !== $label) {
                I18n::save('variant', $vid, ['label' => $labelEn]);
            }
            if ($stock > 0) {
                DB::insert('stock_movements', ['variant_id' => $vid, 'quantity' => $stock, 'reason' => 'initial', 'reference' => null, 'user_id' => null, 'note' => 'Stock initial', 'created_at' => $now]);
            }
        }
    }

    // Code de bienvenue créé désactivé : à activer dans l'administration si l'entreprise le souhaite.
    DB::insert('promo_codes', [
        'code' => 'BIENVENUE10', 'description' => 'Bienvenue : -10 % dès 5 000 FCFA (désactivé par défaut)', 'type' => 'percent', 'value' => 10,
        'min_subtotal' => 5000, 'starts_at' => null, 'ends_at' => null, 'max_uses' => 100, 'used_count' => 0, 'is_active' => 0,
        'created_at' => $now, 'updated_at' => $now,
    ]);
};
