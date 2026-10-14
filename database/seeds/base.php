<?php
declare(strict_types=1);

/**
 * Données de référence indispensables (idempotent) : rôles, permissions, pages, FAQ, zones de livraison.
 * Les contenus marqués [À CONFIRMER] / [À COMPLÉTER] doivent être validés par le client.
 */

use App\Core\DB;

return static function (): void {
    $now = gmdate('Y-m-d H:i:s');

    $permissions = [
        'catalog.manage' => 'Gérer le catalogue, le stock et les promotions',
        'orders.manage' => 'Gérer les commandes, livraisons et devis',
        'customers.view' => 'Consulter les clients',
        'reports.view' => 'Voir le tableau de bord et les rapports',
        'content.manage' => 'Gérer les contenus, pages, FAQ, avis et messages',
        'settings.manage' => 'Modifier les réglages, zones et intégrations',
        'users.manage' => 'Gérer l\'équipe et les rôles',
        'audit.view' => 'Consulter le journal d\'audit',
    ];
    $roles = [
        'admin' => ['Administrateur', array_keys($permissions)],
        'catalog_manager' => ['Gestionnaire catalogue', ['catalog.manage', 'reports.view']],
        'order_manager' => ['Gestionnaire commandes', ['orders.manage', 'customers.view', 'reports.view']],
        'customer' => ['Client', []],
    ];
    foreach ($permissions as $name => $label) {
        if (DB::value('SELECT id FROM permissions WHERE name = ?', [$name]) === null) {
            DB::insert('permissions', ['name' => $name, 'label' => $label]);
        }
    }
    foreach ($roles as $name => [$label, $perms]) {
        $roleId = DB::value('SELECT id FROM roles WHERE name = ?', [$name]);
        if ($roleId === null) {
            $roleId = DB::insert('roles', ['name' => $name, 'label' => $label]);
        }
        foreach ($perms as $p) {
            $pid = (int) DB::value('SELECT id FROM permissions WHERE name = ?', [$p]);
            if (DB::value('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?', [(int) $roleId, $pid]) === null) {
                DB::insert('role_permissions', ['role_id' => (int) $roleId, 'permission_id' => $pid]);
            }
        }
    }

    $legalNotice = "**Modèle provisoire — à faire valider par un juriste au regard du droit ivoirien** (notamment la loi n° 2013-546 relative aux transactions électroniques et la loi n° 2013-450 relative à la protection des données à caractère personnel). [À CONFIRMER]";
    $pages = [
        'a-propos' => ['À propos', false, "## Qui sommes-nous ?\n\nMIEL PUR DE CÔTE D'IVOIRE est une entreprise apicole basée à Katiola. [À COMPLÉTER : date de création, fondateurs, équipe.]\n\n## Nos ruchers\n\n[À COMPLÉTER : localisation des ruchers, nombre de ruches, flore butinée (anacardier, karité, néré…), saisons de récolte. Ne publier que des informations vérifiées.]\n\n## Notre façon de travailler\n\n[À COMPLÉTER : méthodes d'extraction, filtration, conditionnement, contrôles qualité.]\n\n## Certifications et analyses\n\nAucune certification n'est revendiquée sur ce site à ce jour. [À CONFIRMER : ajouter uniquement les certifications ou analyses de laboratoire justifiées par un document.]"],
        'conditions-generales-de-vente' => ['Conditions générales de vente', true, "$legalNotice\n\n## 1. Objet\n\nLes présentes conditions régissent les ventes conclues sur ce site entre [À COMPLÉTER : raison sociale, forme, capital, RCCM, siège] (« le Vendeur ») et toute personne passant commande (« le Client »).\n\n## 2. Produits et prix\n\nLes prix sont indiqués en francs CFA (FCFA), toutes taxes comprises [À CONFIRMER : régime de TVA applicable]. Les frais de livraison sont indiqués avant la validation de la commande.\n\n## 3. Commande\n\nLa commande est enregistrée après validation du récapitulatif. Pour un paiement à la livraison, elle est confirmée par téléphone par le Vendeur.\n\n## 4. Paiement\n\nMoyens acceptés : paiement à la livraison. [À CONFIRMER : autres moyens après intégration effective.]\n\n## 5. Livraison\n\nVoir la [politique de livraison](/politique-de-livraison).\n\n## 6. Rétractation, retours\n\nVoir la [politique de retours et remboursements](/retours-et-remboursements).\n\n## 7. Litiges\n\n[À COMPLÉTER : médiation, juridiction compétente.]"],
        'politique-de-confidentialite' => ['Politique de confidentialité', true, "$legalNotice\n\n## Responsable du traitement\n\n[À COMPLÉTER : identité et coordonnées du responsable ; déclaration auprès de l'ARTCI le cas échéant.]\n\n## Données collectées\n\n- Identité et coordonnées (nom, téléphone, e-mail, adresse de livraison) pour traiter vos commandes ;\n- Historique de commandes si vous créez un compte ;\n- Messages envoyés via les formulaires de contact et de devis ;\n- Adresse e-mail pour la lettre d'information, uniquement avec votre accord.\n\n## Paiement\n\nAucun numéro de carte bancaire n'est collecté ni stocké par ce site.\n\n## Cookies\n\nCookies strictement nécessaires : session, sécurité des formulaires, panier. Les outils de mesure d'audience ne sont chargés qu'après votre consentement.\n\n## Durées de conservation\n\n[À COMPLÉTER]\n\n## Vos droits\n\nVous pouvez demander l'accès, la rectification ou la suppression de vos données en écrivant à [À COMPLÉTER : adresse de contact]."],
        'politique-de-livraison' => ['Politique de livraison', true, "$legalNotice\n\n## Zones desservies\n\nLes zones, frais et délais affichés lors de la commande sont fixés par le Vendeur. [À CONFIRMER : liste définitive des zones et tarifs.]\n\n## Confirmation\n\nPour les commandes payées à la livraison, un conseiller vous appelle pour confirmer l'adresse et le créneau.\n\n## Réception\n\nVérifiez l'état des produits en présence du livreur. [À COMPLÉTER : procédure en cas de colis endommagé.]"],
        'retours-et-remboursements' => ['Retours et remboursements', true, "$legalNotice\n\n## Produits alimentaires\n\n[À CONFIRMER : conditions de retour applicables aux denrées alimentaires (pot non ouvert, délai, état).]\n\n## Produit défectueux ou erreur de commande\n\nContactez-nous dans un délai de [À COMPLÉTER] jours avec votre numéro de commande et une photo.\n\n## Remboursement\n\n[À COMPLÉTER : délai et moyen de remboursement.]"],
        'mentions-legales' => ['Mentions légales', true, "$legalNotice\n\n## Éditeur du site\n\n[À COMPLÉTER : raison sociale, forme juridique, capital, RCCM, NCC, siège social, téléphone, e-mail, directeur de la publication.]\n\n## Hébergement\n\n[À COMPLÉTER : nom, adresse et téléphone de l'hébergeur.]\n\n## Réalisation\n\nSite conçu et développé par HELIX TECHNOLOGIES.\n\n## Propriété intellectuelle\n\nLes textes, logos et photographies sont la propriété de leurs auteurs. Les illustrations marquées « démo » sont provisoires."],
    ];
    foreach ($pages as $slug => [$title, $legal, $body]) {
        if (DB::value('SELECT id FROM pages WHERE slug = ?', [$slug]) === null) {
            DB::insert('pages', [
                'slug' => $slug, 'title' => $title, 'body' => $body, 'is_legal' => $legal ? 1 : 0, 'is_validated' => 0,
                'meta_description' => null, 'updated_by' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    if ((int) DB::value('SELECT COUNT(*) FROM faqs') === 0) {
        $faqs = [
            ['Commande', 'Dois-je créer un compte pour commander ?', 'Non. Vous pouvez commander en tant qu\'invité. Un compte permet simplement de retrouver vos commandes et adresses.'],
            ['Paiement', 'Quels moyens de paiement acceptez-vous ?', 'Le paiement à la livraison est disponible. Les paiements par Orange Money, MTN Mobile Money, Wave ou carte ne sont **pas encore proposés** : ils le seront uniquement après leur intégration et leurs tests. [À CONFIRMER]'],
            ['Livraison', 'Où livrez-vous et en combien de temps ?', 'Les zones, frais et délais sont affichés à l\'étape « Livraison » de la commande. [À CONFIRMER : zones définitives.]'],
            ['Commande', 'Comment suivre ma commande ?', 'Utilisez le lien reçu par e-mail, votre espace client, ou la page [Suivi de commande](/suivi-commande) avec votre numéro et votre e-mail.'],
            ['Produits', 'Comment conserver le miel ?', 'Dans un endroit sec, à température ambiante, à l\'abri de la lumière, pot bien fermé. La cristallisation est un phénomène naturel. [À CONFIRMER par le producteur.]'],
            ['Professionnels', 'Proposez-vous des tarifs pour les professionnels ?', 'Oui, sur devis. Remplissez le formulaire [Commandes professionnelles](/commandes-professionnelles).'],
        ];
        foreach ($faqs as $i => [$cat, $q, $a]) {
            DB::insert('faqs', ['question' => $q, 'answer' => $a, 'category' => $cat, 'sort_order' => $i, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    if ((int) DB::value('SELECT COUNT(*) FROM shipping_zones') === 0) {
        $zones = [
            ['Retrait à Katiola', 'Retrait sur rendez-vous [À CONFIRMER : adresse]', 0, null, 'Sur rendez-vous', 1, 1],
            ['Katiola (livraison locale)', 'Livraison en ville [À CONFIRMER : tarif]', 500, null, '24 à 48 h', 1, 0],
            ['Bouaké', 'Livraison à domicile [À CONFIRMER : tarif]', 1500, null, '2 à 3 jours', 1, 0],
            ['Abidjan', 'Livraison à domicile [À CONFIRMER : tarif]', 2500, 50000, '2 à 4 jours', 1, 0],
            ['Autres villes de Côte d\'Ivoire', 'Expédition par transporteur [À CONFIRMER : tarif, paiement à la livraison possible ?]', 3500, null, '3 à 7 jours', 0, 0],
        ];
        foreach ($zones as $i => [$name, $desc, $fee, $free, $delay, $cod, $pickup]) {
            DB::insert('shipping_zones', [
                'name' => $name, 'description' => $desc, 'fee' => $fee, 'free_threshold' => $free, 'delay_text' => $delay,
                'allows_cod' => $cod, 'is_pickup' => $pickup, 'is_active' => 1, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
};
