<?php
declare(strict_types=1);

/**
 * Réglages administrables et valeurs par défaut.
 * Une clé absente de cette liste est refusée par Settings::set().
 * Les clés suffixées « _en » sont les versions anglaises (affichées sur /en, repli sur le français si vides).
 * Une valeur vide n'est pas affichée sur le site (aucune information inventée).
 */
return [
    // Identité (source publique : annuaire Les Pages Vertes)
    'store_name' => ['label' => 'Nom de la boutique', 'group' => 'Identité', 'default' => "Miel Pur de Côte d'Ivoire"],
    'store_tagline' => ['label' => 'Accroche', 'group' => 'Identité', 'default' => "Miel 100 % bio et cire d'abeille de Katiola"],
    'store_tagline_en' => ['label' => 'Accroche (anglais)', 'group' => 'Identité', 'default' => 'Organic honey and beeswax from Katiola'],
    'store_city' => ['label' => 'Ville', 'group' => 'Identité', 'default' => 'Katiola'],
    'store_address' => ['label' => 'Adresse postale (facultatif)', 'group' => 'Identité', 'default' => ''],
    'store_phone' => ['label' => 'Téléphone', 'group' => 'Identité', 'default' => '+225 07 47 09 90 06'],
    'store_email' => ['label' => 'E-mail public (facultatif)', 'group' => 'Identité', 'default' => ''],
    'whatsapp_number' => ['label' => 'Numéro WhatsApp (format international, chiffres uniquement, ex. 2250171617152)', 'group' => 'Identité', 'default' => ''],
    'opening_hours' => ['label' => 'Horaires (facultatif)', 'group' => 'Identité', 'default' => ''],
    'opening_hours_en' => ['label' => 'Horaires (anglais)', 'group' => 'Identité', 'default' => ''],
    'legal_company' => ['label' => 'Raison sociale / forme juridique (mentions légales)', 'group' => 'Identité', 'default' => ''],
    'legal_rccm' => ['label' => 'N° RCCM', 'group' => 'Identité', 'default' => ''],
    'legal_ncc' => ['label' => 'N° de compte contribuable (NCC)', 'group' => 'Identité', 'default' => ''],
    'legal_director' => ['label' => 'Directeur de la publication', 'group' => 'Identité', 'default' => ''],
    'legal_host' => ['label' => 'Hébergeur (nom, adresse, téléphone)', 'group' => 'Identité', 'default' => ''],
    'social_facebook' => ['label' => 'Lien Facebook', 'group' => 'Réseaux sociaux', 'default' => 'https://www.facebook.com/p/Le-Miel-Pur-de-C%C3%B4te-dIvoire-100062970108746/'],
    'social_instagram' => ['label' => 'Lien Instagram', 'group' => 'Réseaux sociaux', 'default' => ''],
    'social_tiktok' => ['label' => 'Lien TikTok', 'group' => 'Réseaux sociaux', 'default' => ''],

    // Commerce
    'demo_mode' => ['label' => 'Bandeau « site de démonstration » (environnement de test uniquement)', 'group' => 'Commerce', 'default' => '0', 'type' => 'bool'],
    'cod_enabled' => ['label' => 'Paiement à la livraison', 'group' => 'Paiement', 'default' => '1', 'type' => 'bool'],
    'wave_enabled' => ['label' => 'Wave (nécessite WAVE_API_KEY et WAVE_WEBHOOK_SECRET)', 'group' => 'Paiement', 'default' => '1', 'type' => 'bool'],
    'orange_money_enabled' => ['label' => 'Orange Money (nécessite ORANGE_MONEY_* dans .env)', 'group' => 'Paiement', 'default' => '1', 'type' => 'bool'],
    'mtn_momo_enabled' => ['label' => 'MTN Mobile Money (nécessite MTN_MOMO_* dans .env)', 'group' => 'Paiement', 'default' => '1', 'type' => 'bool'],
    'card_enabled' => ['label' => 'Carte bancaire via CinetPay (nécessite CINETPAY_* dans .env)', 'group' => 'Paiement', 'default' => '1', 'type' => 'bool'],
    'demo_payment_enabled' => ['label' => 'Paiement SIMULÉ (tests uniquement — jamais en production)', 'group' => 'Paiement', 'default' => '0', 'type' => 'bool'],
    'min_order_amount' => ['label' => 'Montant minimum de commande (FCFA)', 'group' => 'Commerce', 'default' => '0', 'type' => 'int'],
    'payment_timeout_minutes' => ['label' => 'Délai de paiement en ligne avant annulation (minutes)', 'group' => 'Commerce', 'default' => '60', 'type' => 'int'],
    'low_stock_threshold' => ['label' => 'Seuil d\'alerte stock faible', 'group' => 'Commerce', 'default' => '5', 'type' => 'int'],
    'max_qty_per_line' => ['label' => 'Quantité maximale par article', 'group' => 'Commerce', 'default' => '50', 'type' => 'int'],
    'order_notification_email' => ['label' => 'E-mail de notification des nouvelles commandes', 'group' => 'Commerce', 'default' => ''],

    // Accueil
    'home_hero_title' => ['label' => 'Titre principal', 'group' => 'Accueil', 'default' => 'Le miel pur de Katiola, de nos ruches à votre table'],
    'home_hero_title_en' => ['label' => 'Titre principal (anglais)', 'group' => 'Accueil', 'default' => 'Pure honey from Katiola, from our hives to your table'],
    'home_hero_text' => ['label' => 'Introduction', 'group' => 'Accueil', 'default' => "Nos ruchers sont installés dans la végétation naturelle du nord de la Côte d'Ivoire, où les abeilles trouvent une nourriture riche et variée. Un miel 100 % bio, récolté, conditionné et vendu par nos soins."],
    'home_hero_text_en' => ['label' => 'Introduction (anglais)', 'group' => 'Accueil', 'default' => "Our apiaries stand in the natural vegetation of northern Côte d'Ivoire, where bees find rich and varied forage. 100% organic honey, harvested, packed and sold by us."],
    'home_story_title' => ['label' => 'Titre « Notre démarche »', 'group' => 'Accueil', 'default' => 'Une apiculture de terroir'],
    'home_story_title_en' => ['label' => 'Titre « Notre démarche » (anglais)', 'group' => 'Accueil', 'default' => 'Beekeeping rooted in its land'],
    'home_story_text' => ['label' => 'Texte « Notre démarche »', 'group' => 'Accueil', 'default' => "Notre mission : promouvoir les vertus du miel entièrement produit en Côte d'Ivoire.

La région de Katiola, au centre-nord de la Côte d'Ivoire, est réputée pour la qualité de son miel. Nous y installons nos ruches au cœur d'une végétation naturelle : la diversité des floraisons offre aux abeilles une alimentation variée.\n\nNous assurons nous-mêmes la récolte, le conditionnement et la vente. Notre miel est certifié conforme aux normes internationales par un laboratoire habilité par l'INAO (Institut national de l'origine et de la qualité)."],
    'home_story_text_en' => ['label' => 'Texte « Notre démarche » (anglais)', 'group' => 'Accueil', 'default' => "Our mission: to promote the benefits of honey produced entirely in Côte d'Ivoire.

The Katiola region, in north-central Côte d'Ivoire, is renowned for the quality of its honey. We keep our hives in the heart of natural vegetation, where a wide diversity of flowering plants gives the bees a varied diet.\n\nWe handle the harvest, packing and sale ourselves. Our honey is certified compliant with international standards by a laboratory approved by INAO (the French National Institute of Origin and Quality)."],
    'home_pro_text' => ['label' => 'Texte « Professionnels »', 'group' => 'Accueil', 'default' => 'Hôtels, restaurants, pâtisseries, épiceries fines, cosmétique artisanale : recevez un devis pour des volumes réguliers, en seau ou en bidon.'],
    'home_pro_text_en' => ['label' => 'Texte « Professionnels » (anglais)', 'group' => 'Accueil', 'default' => 'Hotels, restaurants, bakeries, delicatessens, artisanal cosmetics: get a quote for regular volumes, in buckets or drums.'],

    // Mesure d'audience (chargée uniquement après consentement)
    'analytics_provider' => ['label' => 'Mesure d\'audience (none, plausible, ga4)', 'group' => 'Mesure d\'audience', 'default' => 'none', 'options' => ['none', 'plausible', 'ga4']],
    'analytics_id' => ['label' => 'Domaine Plausible ou identifiant GA4 (G-XXXX)', 'group' => 'Mesure d\'audience', 'default' => ''],
];
