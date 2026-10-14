<?php
declare(strict_types=1);

/**
 * Données de référence indispensables (idempotent) : rôles, permissions, pages FR/EN, FAQ FR/EN, zones de livraison FR/EN.
 * Les informations d'identification légale (RCCM, NCC, hébergeur…) proviennent des réglages et ne sont affichées que si elles sont renseignées.
 */

use App\Core\DB;
use App\Services\I18n;

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

    $pages = require __DIR__ . '/pages.php';
    foreach ($pages as $slug => $p) {
        $id = DB::value('SELECT id FROM pages WHERE slug = ?', [$slug]);
        if ($id === null) {
            $id = DB::insert('pages', [
                'slug' => $slug, 'title' => $p['fr'][0], 'body' => $p['fr'][1], 'is_legal' => $p['legal'] ? 1 : 0, 'is_validated' => 0,
                'meta_description' => $p['fr'][2] ?? null, 'updated_by' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            I18n::save('page', (int) $id, ['title' => $p['en'][0], 'body' => $p['en'][1], 'meta_description' => $p['en'][2] ?? '']);
        }
    }

    if ((int) DB::value('SELECT COUNT(*) FROM faqs') === 0) {
        $faqs = [
            [['Commande', 'Dois-je créer un compte pour commander ?', 'Non. Vous pouvez commander en tant qu\'invité. Un compte permet simplement de retrouver vos commandes et vos adresses.'],
             ['Ordering', 'Do I need an account to order?', 'No. You can check out as a guest. An account simply lets you find your orders and addresses again.']],
            [['Paiement', 'Quels moyens de paiement acceptez-vous ?', 'Selon les moyens activés au moment de votre commande : paiement à la livraison, Orange Money, MTN Mobile Money, Wave et carte bancaire. Les moyens disponibles sont affichés à l\'étape « Paiement ». Vos données bancaires sont saisies sur la page sécurisée du prestataire, jamais sur notre site.'],
             ['Payment', 'Which payment methods do you accept?', 'Depending on the methods enabled when you order: cash on delivery, Orange Money, MTN Mobile Money, Wave and bank card. Available methods are shown at the "Payment" step. Your card details are entered on the provider\'s secure page, never on our website.']],
            [['Livraison', 'Où livrez-vous et en combien de temps ?', 'Nous livrons à Katiola, Bouaké, Abidjan et dans les autres villes de Côte d\'Ivoire. Les frais et délais de chaque zone sont affichés à l\'étape « Livraison », avant toute validation. Le retrait à Katiola est gratuit.'],
             ['Delivery', 'Where do you deliver and how long does it take?', 'We deliver to Katiola, Bouaké, Abidjan and other cities in Côte d\'Ivoire. The fee and lead time for each area are shown at the "Delivery" step, before you confirm. Pick-up in Katiola is free.']],
            [['Commande', 'Comment suivre ma commande ?', 'Utilisez le lien reçu par e-mail, votre espace client, ou la page [Suivi de commande](/suivi-commande) avec votre numéro de commande et votre e-mail.'],
             ['Ordering', 'How can I track my order?', 'Use the link in your confirmation email, your account, or the [Order tracking](/suivi-commande) page with your order number and email.']],
            [['Produits', 'Pourquoi mon miel cristallise-t-il ?', 'La cristallisation est un phénomène naturel lié à la composition en sucres du miel : elle ne change ni sa qualité ni ses vertus. Pour le rendre de nouveau liquide, placez le pot fermé au bain-marie tiède (40 °C maximum).'],
             ['Products', 'Why does my honey crystallise?', 'Crystallisation is a natural process caused by the sugars in honey: it changes neither its quality nor its properties. To liquefy it again, warm the closed jar in a lukewarm water bath (40 °C maximum).']],
            [['Produits', 'Comment conserver le miel ?', 'À température ambiante, à l\'abri de l\'humidité et de la lumière, pot bien fermé. Inutile de le mettre au réfrigérateur.'],
             ['Products', 'How should I store honey?', 'At room temperature, away from moisture and light, with the lid tightly closed. There is no need to refrigerate it.']],
            [['Produits', 'Votre miel est-il bio ?', 'Oui. Notre miel est 100 % bio et 100 % miel, et il est certifié conforme aux normes internationales par un laboratoire habilité par l\'INAO.'],
             ['Products', 'Is your honey organic?', 'Yes. Our honey is 100% organic and 100% honey, and it is certified compliant with international standards by an INAO-approved laboratory.']],
            [['Professionnels', 'Proposez-vous des tarifs pour les professionnels ?', 'Oui, sur devis, pour des volumes ponctuels ou réguliers. Remplissez le formulaire [Commandes professionnelles](/commandes-professionnelles).'],
             ['Business', 'Do you offer prices for businesses?', 'Yes, on quotation, for one-off or regular volumes. Fill in the [Business orders](/commandes-professionnelles) form.']],
        ];
        foreach ($faqs as $i => [[$cat, $q, $a], [$catEn, $qEn, $aEn]]) {
            $id = DB::insert('faqs', ['question' => $q, 'answer' => $a, 'category' => $cat, 'sort_order' => $i, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            I18n::save('faq', $id, ['question' => $qEn, 'answer' => $aEn, 'category' => $catEn]);
        }
    }

    if ((int) DB::value('SELECT COUNT(*) FROM shipping_zones') === 0) {
        $zones = [
            [['Retrait à Katiola', 'Retrait sur rendez-vous, après confirmation par téléphone', 'Sur rendez-vous'], ['Pick-up in Katiola', 'Pick-up by appointment, after phone confirmation', 'By appointment'], 0, null, 1, 1],
            [['Katiola', 'Livraison à domicile en ville', '24 à 48 h'], ['Katiola', 'Home delivery in town', '24 to 48 hours'], 500, null, 1, 0],
            [['Bouaké', 'Livraison à domicile', '2 à 3 jours'], ['Bouaké', 'Home delivery', '2 to 3 days'], 1500, null, 1, 0],
            [['Abidjan', 'Livraison à domicile, offerte dès 50 000 FCFA', '2 à 4 jours'], ['Abidjan', 'Home delivery, free from 50,000 FCFA', '2 to 4 days'], 2500, 50000, 1, 0],
            [['Autres villes de Côte d\'Ivoire', 'Expédition par transporteur, paiement en ligne', '3 à 7 jours'], ['Other cities in Côte d\'Ivoire', 'Shipped by carrier, online payment', '3 to 7 days'], 3500, null, 0, 0],
        ];
        foreach ($zones as $i => [[$name, $desc, $delay], [$nameEn, $descEn, $delayEn], $fee, $free, $cod, $pickup]) {
            $id = DB::insert('shipping_zones', [
                'name' => $name, 'description' => $desc, 'fee' => $fee, 'free_threshold' => $free, 'delay_text' => $delay,
                'allows_cod' => $cod, 'is_pickup' => $pickup, 'is_active' => 1, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
            I18n::save('zone', $id, ['name' => $nameEn, 'description' => $descEn, 'delay_text' => $delayEn]);
        }
    }
};
