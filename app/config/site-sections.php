<?php
declare(strict_types=1);

/**
 * Sections de « Paramètres du site » (Administration). Chaque réglage administrable appartient à une seule section ;
 * les textes de l'accueil restent dans Contenus → Accueil (ContentController::HOME_KEYS).
 * links : rubriques liées (gestion dans leur propre écran, pas de système parallèle).
 */
return [
    'identite' => [
        'title' => 'Identité de l\'entreprise',
        'intro' => 'Nom, slogan et présentation affichés sur le site, dans les e-mails et les résultats de recherche.',
        'keys' => ['store_name', 'store_tagline', 'store_tagline_en', 'store_description', 'store_description_en', 'opening_hours', 'opening_hours_en'],
        'links' => [['/admin/pages', 'Présentation détaillée : page « Notre histoire » (Pages et légal)']],
    ],
    'logos' => [
        'title' => 'Logos et images',
        'intro' => 'Logos, favicon, image de partage et image principale de l\'accueil. Sans image téléversée, les fichiers installés avec le site sont utilisés.',
        'keys' => [],
        'images' => ['brand_logo', 'brand_logo_dark', 'brand_logo_footer', 'brand_logo_footer_dark', 'brand_favicon', 'og_image', 'home_hero_image'],
        'links' => [['/admin/carrousel', 'Images des bannières : Carrousel de l\'accueil']],
    ],
    'coordonnees' => [
        'title' => 'Coordonnées et contact',
        'intro' => 'Source unique : en-tête, pied de page, page Contact, fiche produit, panier, e-mails et mentions légales utilisent ces valeurs.',
        'keys' => ['phone_country_code', 'store_phone', 'store_phone_2', 'show_phone', 'whatsapp_number', 'whatsapp_enabled', 'whatsapp_message', 'whatsapp_message_en',
            'store_email', 'sales_email', 'show_email', 'store_address', 'store_city', 'store_country', 'maps_url', 'show_location', 'order_notification_email'],
    ],
    'reseaux' => [
        'title' => 'Réseaux sociaux et liens officiels',
        'intro' => 'Une icône n\'apparaît que si le lien est renseigné et son affichage activé. WhatsApp se règle dans Coordonnées.',
        'keys' => ['social_facebook', 'social_facebook_enabled', 'social_instagram', 'social_instagram_enabled', 'social_tiktok', 'social_tiktok_enabled',
            'social_youtube', 'social_youtube_enabled', 'social_linkedin', 'social_linkedin_enabled', 'social_other', 'social_other_label', 'social_other_enabled', 'social_in_header'],
    ],
    'apparence' => [
        'title' => 'Apparence',
        'intro' => 'Couleurs et polices du thème clair, éléments affichés dans l\'en-tête et le pied de page. Les contrastes sont vérifiés avant l\'enregistrement.',
        'keys' => ['color_primary', 'color_secondary', 'color_accent', 'color_background', 'font_heading', 'font_body',
            'header_show_tagline', 'header_show_search', 'header_show_theme', 'footer_show_newsletter', 'footer_show_payments', 'home_show_payments'],
    ],
    'langues' => [
        'title' => 'Langues',
        'intro' => 'Le français est la langue de référence. L\'anglais n\'est proposé (sélecteur, plan du site) que s\'il est activé ; les textes se traduisent dans chaque fiche (encadré « Traduction anglaise »).',
        'keys' => ['lang_en_enabled', 'lang_default', 'lang_detect_browser'],
    ],
    'commerce' => [
        'title' => 'Livraison, paiements et commandes',
        'intro' => 'Règles de commande. Les moyens de paiement, zones de livraison et devises ont chacun leur écran.',
        'keys' => ['min_order_amount', 'payment_timeout_minutes', 'max_qty_per_line', 'low_stock_threshold', 'delivery_instructions', 'delivery_instructions_en', 'demo_mode', 'demo_payment_enabled'],
        'links' => [['/admin/paiements', 'Moyens de paiement'], ['/admin/livraison', 'Zones de livraison (frais, gratuité, délais)'], ['/admin/devises', 'Devises d\'affichage'], ['/admin/integrations', 'Intégrations et liste de contrôle avant ouverture']],
    ],
    'referencement' => [
        'title' => 'Référencement et partage',
        'intro' => 'Titre et description de l\'accueil pour les moteurs de recherche et les réseaux sociaux. Vide : slogan et texte d\'introduction de l\'accueil. L\'image de partage se règle dans Logos et images.',
        'keys' => ['seo_title', 'seo_title_en', 'seo_description', 'seo_description_en', 'analytics_provider', 'analytics_id'],
    ],
    'mentions' => [
        'title' => 'Politiques et mentions légales',
        'intro' => 'Informations légales de l\'entreprise. Les textes (CGV, confidentialité, livraison, retours, mentions légales) se modifient dans Pages et légal et doivent être validés par l\'entreprise.',
        'keys' => ['legal_company', 'legal_rccm', 'legal_ncc', 'legal_director', 'legal_host'],
        'links' => [['/admin/pages', 'Pages et légal : CGV, confidentialité, livraison, retours, mentions légales'], ['/admin/faq', 'FAQ']],
    ],
];
