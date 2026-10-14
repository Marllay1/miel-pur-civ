<?php
declare(strict_types=1);

/**
 * Réglages administrables et valeurs par défaut.
 * Une clé absente de cette liste est refusée par Settings::set().
 * Toute valeur marquée [À COMPLÉTER] / [À CONFIRMER] doit être renseignée par le client avant la mise en ligne.
 */
return [
    // Identité
    'store_name' => ['label' => 'Nom de la boutique', 'group' => 'Identité', 'default' => "Miel Pur de Côte d'Ivoire"],
    'store_tagline' => ['label' => 'Accroche', 'group' => 'Identité', 'default' => 'Miel et cire d\'abeille de Katiola'],
    'store_city' => ['label' => 'Ville du siège', 'group' => 'Identité', 'default' => 'Katiola'],
    'store_address' => ['label' => 'Adresse postale', 'group' => 'Identité', 'default' => '[À COMPLÉTER]'],
    'store_phone' => ['label' => 'Téléphone', 'group' => 'Identité', 'default' => '[À COMPLÉTER]'],
    'store_email' => ['label' => 'E-mail public', 'group' => 'Identité', 'default' => '[À COMPLÉTER]'],
    'whatsapp_number' => ['label' => 'Numéro WhatsApp (format international, chiffres uniquement)', 'group' => 'Identité', 'default' => ''],
    'opening_hours' => ['label' => 'Horaires', 'group' => 'Identité', 'default' => '[À COMPLÉTER]'],
    'legal_company' => ['label' => 'Raison sociale / forme juridique', 'group' => 'Identité', 'default' => '[À COMPLÉTER]'],
    'legal_rccm' => ['label' => 'N° RCCM', 'group' => 'Identité', 'default' => '[À COMPLÉTER]'],
    'legal_ncc' => ['label' => 'N° de compte contribuable (NCC)', 'group' => 'Identité', 'default' => '[À COMPLÉTER]'],
    'social_facebook' => ['label' => 'Lien Facebook', 'group' => 'Réseaux sociaux', 'default' => ''],
    'social_instagram' => ['label' => 'Lien Instagram', 'group' => 'Réseaux sociaux', 'default' => ''],
    'social_tiktok' => ['label' => 'Lien TikTok', 'group' => 'Réseaux sociaux', 'default' => ''],

    // Commerce
    'demo_mode' => ['label' => 'Bandeau « données de démonstration » (1 = affiché)', 'group' => 'Commerce', 'default' => '1', 'type' => 'bool'],
    'cod_enabled' => ['label' => 'Paiement à la livraison activé', 'group' => 'Commerce', 'default' => '1', 'type' => 'bool'],
    'demo_payment_enabled' => ['label' => 'Paiement SIMULÉ activé (à désactiver en production)', 'group' => 'Commerce', 'default' => '1', 'type' => 'bool'],
    'min_order_amount' => ['label' => 'Montant minimum de commande (FCFA)', 'group' => 'Commerce', 'default' => '0', 'type' => 'int'],
    'payment_timeout_minutes' => ['label' => 'Délai de paiement en ligne avant annulation (minutes)', 'group' => 'Commerce', 'default' => '60', 'type' => 'int'],
    'low_stock_threshold' => ['label' => 'Seuil d\'alerte stock faible', 'group' => 'Commerce', 'default' => '5', 'type' => 'int'],
    'max_qty_per_line' => ['label' => 'Quantité maximale par article', 'group' => 'Commerce', 'default' => '50', 'type' => 'int'],
    'order_notification_email' => ['label' => 'E-mail de notification des nouvelles commandes', 'group' => 'Commerce', 'default' => ''],

    // Accueil
    'home_hero_title' => ['label' => 'Titre principal de l\'accueil', 'group' => 'Accueil', 'default' => 'Le miel de Katiola, du rucher à votre table'],
    'home_hero_text' => ['label' => 'Texte d\'introduction', 'group' => 'Accueil', 'default' => "Miel et cire d'abeille récoltés par une entreprise apicole ivoirienne. Commandez en ligne, payez à la livraison. [À CONFIRMER : texte de présentation à valider par le client]"],
    'home_story_title' => ['label' => 'Titre « Notre démarche »', 'group' => 'Accueil', 'default' => 'Une apiculture de terroir'],
    'home_story_text' => ['label' => 'Texte « Notre démarche »', 'group' => 'Accueil', 'default' => "[À COMPLÉTER : histoire de l'entreprise, nombre de ruches, méthodes de récolte, zones de butinage. Aucune information n'est affichée tant qu'elle n'a pas été fournie et vérifiée.]"],
    'home_pro_text' => ['label' => 'Texte « Commandes professionnelles »', 'group' => 'Accueil', 'default' => 'Hôtels, restaurants, pâtisseries, épiceries fines, cosmétique artisanale : demandez un devis pour des volumes réguliers.'],

    // Mesure d'audience (chargée uniquement après consentement)
    'analytics_provider' => ['label' => 'Mesure d\'audience (none, plausible, ga4)', 'group' => 'Mesure d\'audience', 'default' => 'none', 'options' => ['none', 'plausible', 'ga4']],
    'analytics_id' => ['label' => 'Domaine Plausible ou identifiant GA4 (G-XXXX)', 'group' => 'Mesure d\'audience', 'default' => ''],
];
