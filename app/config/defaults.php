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
    'store_city' => ['label' => 'Ville', 'group' => 'Coordonnées', 'default' => 'Katiola'],
    'store_address' => ['label' => 'Adresse physique (facultatif)', 'group' => 'Coordonnées', 'default' => ''],
    'store_phone' => ['label' => "Téléphone principal (sans l'indicatif)", 'group' => 'Coordonnées', 'default' => '07 47 09 90 06', 'type' => 'phone'],
    'store_email' => ['label' => 'E-mail de contact (public)', 'group' => 'Coordonnées', 'default' => '', 'type' => 'email'],
    'whatsapp_number' => ['label' => "Numéro WhatsApp (sans l'indicatif)", 'group' => 'Coordonnées', 'default' => '', 'type' => 'phone'],
    'opening_hours' => ['label' => 'Horaires (facultatif)', 'group' => 'Identité', 'default' => ''],
    'opening_hours_en' => ['label' => 'Horaires (anglais)', 'group' => 'Identité', 'default' => ''],
    'legal_company' => ['label' => 'Raison sociale / forme juridique (mentions légales)', 'group' => 'Identité', 'default' => ''],
    'legal_rccm' => ['label' => 'N° RCCM', 'group' => 'Identité', 'default' => ''],
    'legal_ncc' => ['label' => 'N° de compte contribuable (NCC)', 'group' => 'Identité', 'default' => ''],
    'legal_director' => ['label' => 'Directeur de la publication', 'group' => 'Identité', 'default' => ''],
    'legal_host' => ['label' => 'Hébergeur (nom, adresse, téléphone)', 'group' => 'Identité', 'default' => ''],
    'social_facebook' => ['label' => 'Lien Facebook', 'group' => 'Réseaux sociaux', 'default' => 'https://www.facebook.com/p/Le-Miel-Pur-de-C%C3%B4te-dIvoire-100062970108746/', 'type' => 'url'],
    'social_instagram' => ['label' => 'Lien Instagram', 'group' => 'Réseaux sociaux', 'default' => '', 'type' => 'url'],
    'social_tiktok' => ['label' => 'Lien TikTok', 'group' => 'Réseaux sociaux', 'default' => '', 'type' => 'url'],

    // Commerce
    'demo_mode' => ['label' => 'Bandeau « site de démonstration » (environnement de test uniquement)', 'group' => 'Commerce', 'default' => '0', 'type' => 'bool'],
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
    'analytics_provider' => ['label' => 'Mesure d\'audience (none, plausible, ga4)', 'group' => 'Référencement', 'default' => 'none', 'options' => ['none', 'plausible', 'ga4']],
    'analytics_id' => ['label' => 'Domaine Plausible ou identifiant GA4 (G-XXXX)', 'group' => 'Référencement', 'default' => ''],
    // Identité (compléments)
    'store_description' => ['label' => 'Description courte (pied de page, partage)', 'group' => 'Identité', 'default' => "Miel 100 % bio et cire d'abeille, récoltés dans nos ruchers de la région de Katiola, au centre-nord de la Côte d'Ivoire. Notre mission : promouvoir les vertus du miel entièrement produit en Côte d'Ivoire.", 'type' => 'text'],
    'store_description_en' => ['label' => 'Description courte (anglais)', 'group' => 'Identité', 'default' => "100% organic honey and beeswax, harvested from our apiaries in the Katiola region, north-central Côte d'Ivoire. Our mission: to promote the benefits of honey produced entirely in Côte d'Ivoire.", 'type' => 'text'],
    'store_country' => ['label' => 'Pays', 'group' => 'Coordonnées', 'default' => "Côte d'Ivoire"],

    // Coordonnées (une seule source pour tout le site)
    'phone_country_code' => ['label' => 'Indicatif international (ex. +225)', 'group' => 'Coordonnées', 'default' => '+225', 'type' => 'dialcode'],
    'store_phone_2' => ['label' => 'Téléphone secondaire (facultatif)', 'group' => 'Coordonnées', 'default' => '', 'type' => 'phone'],
    'show_phone' => ['label' => 'Afficher les numéros de téléphone sur le site', 'group' => 'Coordonnées', 'default' => '1', 'type' => 'bool'],
    'whatsapp_enabled' => ['label' => 'Afficher les boutons WhatsApp', 'group' => 'Coordonnées', 'default' => '1', 'type' => 'bool'],
    'whatsapp_message' => ['label' => 'Message prérempli WhatsApp', 'group' => 'Coordonnées', 'default' => 'Bonjour, je souhaite des informations sur vos produits.'],
    'whatsapp_message_en' => ['label' => 'Message prérempli WhatsApp (anglais)', 'group' => 'Coordonnées', 'default' => 'Hello, I would like some information about your products.'],
    'sales_email' => ['label' => 'E-mail commercial / commandes (facultatif, public)', 'group' => 'Coordonnées', 'default' => '', 'type' => 'email'],
    'show_email' => ['label' => 'Afficher les adresses e-mail sur le site', 'group' => 'Coordonnées', 'default' => '1', 'type' => 'bool'],
    'maps_url' => ['label' => 'Lien Google Maps (facultatif)', 'group' => 'Coordonnées', 'default' => '', 'type' => 'url'],
    'show_location' => ['label' => 'Afficher la localisation (adresse, ville, pays)', 'group' => 'Coordonnées', 'default' => '1', 'type' => 'bool'],

    // Réseaux sociaux (compléments) : un réseau n'apparaît que si son lien est renseigné ET son affichage activé
    'social_youtube' => ['label' => 'Lien YouTube', 'group' => 'Réseaux sociaux', 'default' => '', 'type' => 'url'],
    'social_linkedin' => ['label' => 'Lien LinkedIn', 'group' => 'Réseaux sociaux', 'default' => '', 'type' => 'url'],
    'social_other' => ['label' => 'Autre site ou réseau (lien)', 'group' => 'Réseaux sociaux', 'default' => '', 'type' => 'url'],
    'social_other_label' => ['label' => 'Autre site ou réseau (nom affiché)', 'group' => 'Réseaux sociaux', 'default' => ''],
    'social_facebook_enabled' => ['label' => 'Afficher Facebook', 'group' => 'Réseaux sociaux', 'default' => '1', 'type' => 'bool'],
    'social_instagram_enabled' => ['label' => 'Afficher Instagram', 'group' => 'Réseaux sociaux', 'default' => '1', 'type' => 'bool'],
    'social_tiktok_enabled' => ['label' => 'Afficher TikTok', 'group' => 'Réseaux sociaux', 'default' => '1', 'type' => 'bool'],
    'social_youtube_enabled' => ['label' => 'Afficher YouTube', 'group' => 'Réseaux sociaux', 'default' => '1', 'type' => 'bool'],
    'social_linkedin_enabled' => ['label' => 'Afficher LinkedIn', 'group' => 'Réseaux sociaux', 'default' => '1', 'type' => 'bool'],
    'social_other_enabled' => ['label' => "Afficher l'autre lien", 'group' => 'Réseaux sociaux', 'default' => '1', 'type' => 'bool'],
    'social_in_header' => ['label' => "Afficher aussi les icônes sociales dans l'en-tête (ordinateur)", 'group' => 'Réseaux sociaux', 'default' => '0', 'type' => 'bool'],

    // Apparence (contrastes vérifiés à l'enregistrement)
    'color_primary' => ['label' => 'Couleur principale (boutons, liens, titres)', 'group' => 'Apparence', 'default' => '#184626', 'type' => 'color'],
    'color_secondary' => ['label' => 'Couleur secondaire (or miel : accents, pastilles)', 'group' => 'Apparence', 'default' => '#E9A50A', 'type' => 'color'],
    'color_accent' => ['label' => "Couleur d'accent (détails décoratifs uniquement, jamais pour du texte)", 'group' => 'Apparence', 'default' => '#EC7110', 'type' => 'color'],
    'color_background' => ['label' => 'Couleur de fond (thème clair)', 'group' => 'Apparence', 'default' => '#FBF8F0', 'type' => 'color'],
    'font_heading' => ['label' => 'Police des titres', 'group' => 'Apparence', 'default' => 'young-serif', 'options' => ['young-serif', 'figtree', 'georgia', 'palatino', 'system']],
    'font_body' => ['label' => 'Police du texte', 'group' => 'Apparence', 'default' => 'figtree', 'options' => ['figtree', 'system', 'georgia', 'verdana']],
    'header_show_tagline' => ['label' => "Afficher le slogan au-dessus du titre de l'accueil", 'group' => 'Apparence', 'default' => '0', 'type' => 'bool'],
    'header_show_search' => ['label' => 'En-tête : bouton de recherche', 'group' => 'Apparence', 'default' => '1', 'type' => 'bool'],
    'header_show_theme' => ['label' => 'En-tête : bouton clair / sombre', 'group' => 'Apparence', 'default' => '1', 'type' => 'bool'],
    'footer_show_newsletter' => ['label' => "Pied de page : inscription à la lettre d'information", 'group' => 'Apparence', 'default' => '1', 'type' => 'bool'],
    'footer_show_payments' => ['label' => 'Pied de page : logos « Paiements acceptés »', 'group' => 'Apparence', 'default' => '1', 'type' => 'bool'],
    'home_show_payments' => ['label' => 'Accueil : logos des moyens de paiement', 'group' => 'Apparence', 'default' => '1', 'type' => 'bool'],

    // Langues (le français est la langue de référence ; l'anglais n'est proposé que s'il est activé)
    'lang_en_enabled' => ['label' => 'Proposer la version anglaise', 'group' => 'Langues', 'default' => '1', 'type' => 'bool'],
    'lang_default' => ['label' => "Langue de l'accueil pour un nouveau visiteur", 'group' => 'Langues', 'default' => 'fr', 'options' => ['fr', 'en']],
    'lang_detect_browser' => ['label' => "Ouvrir l'accueil dans la langue du navigateur du visiteur (si elle est proposée)", 'group' => 'Langues', 'default' => '0', 'type' => 'bool'],

    // Référencement et partage (vide = accroche et texte d'introduction de l'accueil)
    'seo_title' => ['label' => 'Titre du site (onglet, moteurs de recherche)', 'group' => 'Référencement', 'default' => ''],
    'seo_title_en' => ['label' => 'Titre du site (anglais)', 'group' => 'Référencement', 'default' => ''],
    'seo_description' => ['label' => "Méta-description de l'accueil (160 caractères conseillés)", 'group' => 'Référencement', 'default' => '', 'type' => 'text'],
    'seo_description_en' => ['label' => 'Méta-description (anglais)', 'group' => 'Référencement', 'default' => '', 'type' => 'text'],

    // Images de marque (Administration → Paramètres du site → Logos et images ; vide = fichiers installés par bin/brand.php)
    'brand_logo' => ['label' => "Logo de l'en-tête", 'group' => 'Images', 'default' => '', 'type' => 'image'],
    'brand_logo_dark' => ['label' => "Logo de l'en-tête — version pour le thème sombre", 'group' => 'Images', 'default' => '', 'type' => 'image'],
    'brand_logo_footer' => ['label' => 'Logo du pied de page et des pages de connexion', 'group' => 'Images', 'default' => '', 'type' => 'image'],
    'brand_logo_footer_dark' => ['label' => 'Logo du pied de page — thème sombre', 'group' => 'Images', 'default' => '', 'type' => 'image'],
    'brand_favicon' => ['label' => 'Emblème carré pour le favicon', 'group' => 'Images', 'default' => '', 'type' => 'image'],
    'og_image' => ['label' => 'Image de partage sur les réseaux sociaux (1200 × 630 conseillé)', 'group' => 'Images', 'default' => '', 'type' => 'image'],
    'home_hero_image' => ['label' => "Image principale de l'accueil (affichée sans diapositive active)", 'group' => 'Images', 'default' => '', 'type' => 'image'],

    // Livraison et carrousel
    'delivery_instructions' => ['label' => 'Instructions de livraison affichées au client (facultatif)', 'group' => 'Commerce', 'default' => '', 'type' => 'text'],
    'delivery_instructions_en' => ['label' => 'Instructions de livraison (anglais)', 'group' => 'Commerce', 'default' => '', 'type' => 'text'],
    'carousel_autoplay' => ['label' => 'Défilement automatique', 'group' => 'Carrousel', 'default' => '1', 'type' => 'bool'],
    'carousel_interval' => ['label' => "Durée d'affichage d'une diapositive (secondes, 4 à 15)", 'group' => 'Carrousel', 'default' => '7', 'type' => 'int'],
];
