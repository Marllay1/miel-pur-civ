<?php
declare(strict_types=1);

/**
 * Les 18 scénarios du cahier des charges (+ contrôles de sécurité).
 * Chaque scénario démarre sur une base fraîche (schéma + données de référence + démo).
 */

use App\Core\Auth;
use App\Core\DB;
use App\Core\Env;
use App\Core\Mailer;
use App\Services\OrderService;
use App\Services\PaymentService;

return [

    '01 Catalogue : liste, filtres, tri, recherche, pagination' => function (): void {
        $c = new Client();
        $r = $c->get('/boutique');
        T::status(200, $r, 'Boutique');
        T::contains('5 produits', $r->body, 'Les 5 produits de démo sont listés');
        $r = $c->get('/boutique?categorie=miels&tri=prix_desc');
        T::contains('3 produits', $r->body, 'Filtre catégorie');
        T::ok(strpos($r->body, 'Miel en vrac') < strpos($r->body, 'Miel crémeux'), 'Tri prix décroissant');
        T::contains('2 produits', $c->get('/boutique?format=pot-verre')->body, 'Filtre format');
        T::contains('2 produits', $c->get('/boutique?q=cire')->body, 'Recherche plein texte (pain de cire + coffret qui en contient)');
        T::contains('2 produits', $c->get('/boutique?prix_min=10000')->body, 'Filtre prix minimum (variante la plus chère >= 10 000)');
        T::contains('5 produits', $c->get('/boutique?en_stock=1')->body, 'Filtre en stock');
        T::contains('0 produit', $c->get('/boutique?q=%25%25')->body, 'Caractères LIKE échappés');
        T::status(404, $c->get('/boutique?categorie=inconnue'), 'Catégorie inconnue → 404');
        DB::run("UPDATE products SET is_active = 0 WHERE slug = 'coffret-decouverte'");
        T::contains('4 produits', $c->get('/boutique')->body, 'Produit masqué exclu');
        for ($i = 0; $i < 12; $i++) {
            DB::insert('products', ['category_id' => 1, 'name' => "Produit pagination $i", 'slug' => "pagination-$i", 'is_active' => 1, 'is_featured' => 0, 'is_demo' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::insert('product_variants', ['product_id' => (int) DB::pdo()->lastInsertId(), 'label' => '1', 'format' => 'pot-verre', 'sku' => "PAG-$i", 'price' => 1000, 'stock' => 1, 'is_active' => 1, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $p2 = $c->get('/boutique?page=2');
        T::status(200, $p2, 'Page 2');
        T::contains('aria-current="page">2<', $p2->body, 'Pagination active');
        T::contains('noindex', $p2->body, 'Pages de pagination non indexées');
    },

    '02 Fiche produit : variantes, prix, stock, SEO, 404' => function (): void {
        $c = new Client();
        $r = $c->get('/boutique/miel-liquide-toutes-fleurs');
        T::status(200, $r, 'Fiche produit');
        T::contains('2 500 FCFA', html_entity_decode($r->body), 'Prix de la variante');
        T::contains('name="variant_id"', $r->body, 'Sélecteur de variantes');
        T::contains('"@type":"Product"', $r->body, 'JSON-LD Product');
        T::contains('"priceCurrency":"XOF"', $r->body, 'Devise XOF');
        T::notContains('aggregateRating', $r->body, 'Aucune note inventée sans avis');
        T::contains('Aucun avis publié', $r->body, 'Absence d\'avis assumée');
        T::contains('og:title', $r->body, 'Open Graph');
        T::contains('rel="canonical"', $r->body, 'URL canonique');
        T::notContains('émo', strip_tags($r->body), 'Plus aucune mention « démo »');
        T::notContains('À CONFIRMER', $r->body, 'Plus aucun marqueur provisoire');
        $vrac = $c->get('/boutique/miel-vrac-professionnels');
        T::ok(preg_match('/value="' . T::variant('MPCI-VRAC-25')['id'] . '"[^>]*disabled/s', $vrac->body) === 1, 'Variante en rupture désactivée');
        T::status(404, $c->get('/boutique/nexiste-pas'), 'Produit inexistant');
        DB::run("UPDATE products SET is_active = 0 WHERE slug = 'miel-cremeux'");
        T::status(404, $c->get('/boutique/miel-cremeux'), 'Produit masqué inaccessible');
    },

    '03 Panier : ajout, modification, retrait, persistance, promo, fusion' => function (): void {
        $c = new Client();
        $r = $c->addToCart('MPCI-LIQ-500', 2);
        T::redirectsTo('/panier', $r, 'Ajout redirige vers le panier');
        T::ok(isset($c->cookies['mpci_cart']) && preg_match('/^[a-f0-9]{48}$/', $c->cookies['mpci_cart']) === 1, 'Cookie opaque 48 hex');
        $json = $c->post('/panier/ajouter', ['variant_id' => (string) T::variant('MPCI-CIRE-250')['id'], 'qty' => '1'], true, ['Accept' => 'application/json']);
        T::status(200, $json, 'Ajout AJAX');
        T::eq(3, json_decode($json->body, true)['count'], 'Compteur renvoyé');
        $cart = $c->get('/panier');
        T::contains('12 500', html_entity_decode($cart->body), 'Sous-total 2×4 500 + 3 500');
        $c->post('/panier/modifier', ['variant_id' => (string) T::variant('MPCI-LIQ-500')['id'], 'qty' => '1']);
        T::contains('8 000', html_entity_decode($c->get('/panier')->body), 'Quantité modifiée');
        $c->post('/panier/retirer', ['variant_id' => (string) T::variant('MPCI-CIRE-250')['id']]);
        T::contains('4 500', html_entity_decode($c->get('/panier')->body), 'Article retiré');
        // Persistance : un autre « navigateur » avec le même cookie retrouve le panier.
        $other = new Client();
        $other->cookies = $c->cookies;
        T::contains('Miel liquide toutes fleurs', $other->get('/panier')->body, 'Panier persistant (cookie)');
        // Code promo : minimum 5 000 non atteint, puis atteint.
        $c->post('/panier/code-promo', ['code' => 'bienvenue10']);
        T::notContains('Remise (BIENVENUE10)', $c->get('/panier')->body, 'Promo refusée sous le minimum');
        $c->addToCart('MPCI-LIQ-500', 1);
        $c->post('/panier/code-promo', ['code' => 'bienvenue10']);
        $page = html_entity_decode($c->get('/panier')->body);
        T::contains('Remise (BIENVENUE10)', $page, 'Promo appliquée');
        T::contains('900', $page, '10 % de 9 000');
        // Fusion à la connexion.
        $u = T::user('customer', 'fusion@example.test');
        $login = $c->post('/compte/connexion', ['email' => 'fusion@example.test', 'password' => 'Motdepasse123']);
        T::redirectsTo('/compte', $login, 'Connexion');
        T::eq(1, (int) DB::value('SELECT COUNT(*) FROM carts WHERE user_id = ?', [(int) $u['id']]), 'Panier rattaché au compte');
        T::contains('Miel liquide toutes fleurs', $c->get('/panier')->body, 'Panier conservé après connexion');
    },

    '04 Refus de stock' => function (): void {
        $c = new Client();
        $r = $c->post('/panier/ajouter', ['variant_id' => (string) T::variant('MPCI-CRE-500')['id'], 'qty' => '4'], true, ['Accept' => 'application/json']);
        T::status(422, $r, 'Quantité > stock refusée');
        T::contains('Stock insuffisant', json_decode($r->body, true)['message'], 'Message explicite');
        $r = $c->post('/panier/ajouter', ['variant_id' => (string) T::variant('MPCI-VRAC-25')['id'], 'qty' => '1'], true, ['Accept' => 'application/json']);
        T::status(422, $r, 'Rupture refusée');
        $r = $c->post('/panier/ajouter', ['variant_id' => (string) T::variant('MPCI-LIQ-250')['id'], 'qty' => '0'], true, ['Accept' => 'application/json']);
        T::status(422, $r, 'Quantité nulle refusée');
        $c->addToCart('MPCI-CRE-500', 3);
        DB::run("UPDATE product_variants SET stock = 1 WHERE sku = 'MPCI-CRE-500'");
        T::redirectsTo('/panier', $c->get('/commande'), 'Commande bloquée si le stock a baissé');
        T::contains('Stock insuffisant', $c->get('/panier')->body, 'Panier signale le problème');
    },

    '05 Commande valide (paiement à la livraison)' => function (): void {
        $c = new Client();
        $c->addToCart('MPCI-LIQ-500', 2);
        $before = T::stock('MPCI-LIQ-500');
        $r = $c->checkout('Abidjan', 'cod');
        [$number, $token] = Client::orderRef($r);
        $o = OrderService::findByNumber($number);
        T::eq('pending_confirmation', $o['status'], 'Statut initial COD');
        T::eq('unpaid', $o['payment_status'], 'Non payée');
        T::eq(9000 + 2500, (int) $o['total'], 'Total = 9 000 + 2 500 de livraison');
        T::eq($before - 2, T::stock('MPCI-LIQ-500'), 'Stock décrémenté');
        $item = DB::one('SELECT * FROM order_items WHERE order_id = ?', [(int) $o['id']]);
        T::eq(4500, (int) $item['unit_price'], 'Instantané du prix');
        T::eq('Miel liquide toutes fleurs', $item['product_name'], 'Instantané du nom');
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM stock_movements WHERE reason = 'order' AND reference = ?", [$number]), 'Mouvement de stock');
        T::eq(1, (int) DB::value('SELECT COUNT(*) FROM order_status_history WHERE order_id = ?', [(int) $o['id']]), 'Historique initial');
        T::eq(0, (int) DB::value('SELECT COUNT(*) FROM cart_items'), 'Panier vidé');
        T::ok(count(Mailer::$sent) >= 1 && Mailer::$sent[0]['to'] === 'awa@example.test', 'E-mail de confirmation');
        T::contains('?t=' . $token, Mailer::$sent[0]['body'], 'Lien personnel dans l\'e-mail');
        T::ok($o['access_token_hash'] === hash('sha256', $token) && !str_contains(json_encode($o), $token), 'Jeton stocké haché uniquement');
        $page = $c->get('/commande/' . $number . '?t=' . $token);
        T::status(200, $page, 'Page de commande');
        T::contains('Paiement à la livraison', $page->body, 'Mode de paiement affiché');
        T::status(200, $c->get('/commande/' . $number . '/recu?t=' . $token), 'Récapitulatif imprimable');
        // Retrait : adresse non requise.
        $c2 = new Client('10.0.0.2');
        $c2->addToCart('MPCI-LIQ-250', 1);
        $r2 = $c2->checkout('Retrait à Katiola', 'cod', ['city' => '', 'address' => '']);
        [$n2] = Client::orderRef($r2);
        T::eq(1, (int) OrderService::findByNumber($n2)['is_pickup'], 'Commande en retrait sans adresse');
    },

    '06 Refus de prix falsifié' => function (): void {
        $c = new Client();
        $c->addToCart('MPCI-LIQ-500', 1);
        $before = T::stock('MPCI-LIQ-500');
        $r = $c->checkout('Abidjan', 'cod', ['expected_total' => '100']);
        T::status(409, $r, 'Total falsifié refusé');
        T::contains('recalculé', html_entity_decode($r->body), 'Message de recalcul');
        T::eq(0, (int) DB::value('SELECT COUNT(*) FROM orders'), 'Aucune commande créée');
        T::eq($before, T::stock('MPCI-LIQ-500'), 'Stock inchangé');
        // Champs de prix injectés : ignorés, le serveur recalcule.
        $r = $c->checkout('Abidjan', 'cod', ['price' => '1', 'unit_price' => '1', 'total' => '1', 'shipping_fee' => '0', 'discount' => '999999']);
        [$number] = Client::orderRef($r);
        $o = OrderService::findByNumber($number);
        T::eq(4500 + 2500, (int) $o['total'], 'Total serveur malgré les champs injectés');
        T::eq(0, (int) $o['discount'], 'Remise injectée ignorée');
        // Paiement à la livraison interdit pour une zone qui ne l'autorise pas.
        $c->addToCart('MPCI-LIQ-500', 1);
        T::status(422, $c->checkout('Autres villes de Côte d\'Ivoire', 'cod'), 'Moyen de paiement non autorisé pour la zone');
        T::status(422, $c->checkout('Abidjan', 'orange_money'), 'Prestataire non intégré refusé');
        // Zone payable uniquement en ligne : proposée seulement si un paiement en ligne est disponible.
        $c->addToCart('MPCI-LIQ-500', 1);
        T::contains('Autres villes', $c->get('/commande')->body, 'Zone « paiement en ligne » proposée quand un paiement en ligne existe');
        Env::set('DEMO_PAYMENT_ENABLED', '0');
        $form = $c->get('/commande')->body;
        T::notContains('Autres villes', $form, 'Zone sans aucun moyen de paiement utilisable non proposée (client jamais bloqué)');
        T::contains('Abidjan', $form, 'Zones payables à la livraison toujours proposées');
        T::status(422, $c->checkout('Autres villes de Côte d\'Ivoire', 'demo'), 'Commande refusée côté serveur pour cette zone');
        Env::set('DEMO_PAYMENT_ENABLED', '1');
    },

    '07 Double commande (idempotence)' => function (): void {
        $c = new Client();
        $c->addToCart('MPCI-LIQ-500', 1);
        $c->get('/commande');
        $key = $c->session['checkout_key'];
        $z = T::zone('Abidjan');
        $data = ['idempotency_key' => $key, 'expected_total' => '7000', 'first_name' => 'Awa', 'last_name' => 'K', 'email' => 'awa@example.test',
            'phone' => '0700000001', 'zone_id' => (string) $z['id'], 'city' => 'Abidjan', 'district' => '', 'address' => 'Rue', 'landmark' => '',
            'payment_method' => 'cod', 'note' => '', 'cgv' => '1'];
        $first = $c->post('/commande', $data);
        [$number] = Client::orderRef($first);
        $second = $c->post('/commande', $data);
        T::redirectsTo('/commande/' . $number, $second, 'Seconde soumission → même commande');
        T::eq(1, (int) DB::value('SELECT COUNT(*) FROM orders'), 'Une seule commande');
        T::eq(29, T::stock('MPCI-LIQ-500'), 'Stock décrémenté une seule fois');
        // Appel direct concurrent avec la même clé : la contrainte UNIQUE renvoie la commande existante.
        $again = OrderService::create(['zone_id' => $z['id'], 'payment_method' => 'cod'] + $data, null, null, $key, 7000);
        T::eq('existing', $again['status'], 'Service : commande existante renvoyée');
        T::ok($c->session['checkout_key'] !== $key, 'Nouvelle clé générée après commande');
    },

    '08 Paiement : en attente, échec, réussite (SIMULÉ via HTTP)' => function (): void {
        $c = new Client();
        $c->addToCart('MPCI-CIRE-250', 2);
        [$number, $token] = Client::orderRef($c->checkout('Abidjan', 'demo'));
        $o = OrderService::findByNumber($number);
        T::eq('pending_payment', $o['status'], 'En attente de paiement');
        T::eq('pending', $o['payment_status'], 'Paiement en attente');
        T::ok($o['expires_at'] !== null, 'Échéance fixée');
        $page = $c->get("/commande/$number?t=$token");
        T::contains('Paiement SIMULÉ', $page->body, 'Bouton de paiement étiqueté SIMULÉ');
        $go = $c->post("/commande/$number/payer", ['t' => $token]);
        T::redirectsTo('/paiement/demo/PAY-', $go, 'Redirection vers la page simulée');
        $demoUrl = $go->headers['Location'];
        $ref = explode('?', basename($demoUrl))[0];
        $demo = $c->get($demoUrl);
        T::status(200, $demo, 'Page de paiement simulé');
        T::contains('aucune somme n\'est débitée', html_entity_decode($demo->body, ENT_QUOTES), 'Avertissement simulé');
        $c->post('/paiement/demo/' . $ref, ['t' => $token, 'outcome' => 'failed']);
        $o = OrderService::findByNumber($number);
        T::eq('pending_payment', $o['status'], 'Échec : commande toujours en attente');
        T::eq('failed', $o['payment_status'], 'Échec enregistré');
        $go2 = $c->post("/commande/$number/payer", ['t' => $token]);
        $ref2 = explode('?', basename($go2->headers['Location']))[0];
        T::ok($ref2 !== $ref, 'Nouvelle tentative = nouvelle référence');
        $c->post('/paiement/demo/' . $ref2, ['t' => $token, 'outcome' => 'succeeded']);
        $o = OrderService::findByNumber($number);
        T::eq('paid', $o['status'], 'Réussite : commande payée');
        T::eq('paid', $o['payment_status'], 'Paiement payé');
        T::ok($o['paid_at'] !== null, 'Date de paiement');
        T::status(404, (new Client('10.9.9.9'))->get(strtok($demoUrl, '?')), 'Page simulée inaccessible sans jeton');
        // Expiration : commande non payée annulée, stock restitué.
        $c->addToCart('MPCI-CIRE-250', 1);
        [$n3] = Client::orderRef($c->checkout('Abidjan', 'demo'));
        $stockBefore = T::stock('MPCI-CIRE-250');
        DB::run('UPDATE orders SET expires_at = ? WHERE number = ?', [gmdate('Y-m-d H:i:s', time() - 60), $n3]);
        T::eq(1, OrderService::expireOverdue(), 'Une commande expirée');
        T::eq('cancelled', OrderService::findByNumber($n3)['status'], 'Commande expirée annulée');
        T::eq($stockBefore + 1, T::stock('MPCI-CIRE-250'), 'Stock restitué à l\'expiration');
        // Désactivation du paiement simulé : plus proposé.
        Env::set('DEMO_PAYMENT_ENABLED', '0');
        T::eq(['cod'], array_column(PaymentService::availableMethods(T::zone('Abidjan')), 'code'), 'Seul le paiement à la livraison reste proposé');
    },

    '09 Webhook signé : signature, horodatage, rejeu, montant' => function (): void {
        $c = new Client();
        $c->addToCart('MPCI-CIRE-250', 1);
        [$number, $token] = Client::orderRef($c->checkout('Abidjan', 'demo'));
        $order = OrderService::findByNumber($number);
        $payment = PaymentService::initiate($order, $token)['payment'];
        $gw = PaymentService::gateway('demo');
        $wh = new Client('41.0.0.1');
        $n = $gw->buildNotification($payment, 'succeeded');
        $bad = $n['headers'];
        $bad['x-demo-signature'] = str_repeat('0', 64);
        T::status(401, $wh->request('POST', '/webhooks/paiement/demo', [], $bad, $n['body']), 'Signature invalide → 401');
        T::status(400, $wh->request('POST', '/webhooks/paiement/demo', [], [], $n['body']), 'Signature absente → 400');
        $old = $gw->buildNotification($payment, 'succeeded', time() - 600);
        T::status(400, $wh->request('POST', '/webhooks/paiement/demo', [], $old['headers'], $old['body']), 'Horodatage expiré → 400');
        $tampered = json_decode($n['body'], true);
        $tampered['amount'] = 1;
        T::status(401, $wh->request('POST', '/webhooks/paiement/demo', [], $n['headers'], json_encode($tampered)), 'Corps modifié → signature invalide');
        T::eq('pending_payment', OrderService::findByNumber($number)['status'], 'Aucun effet des notifications refusées');
        $mismatch = $gw->buildNotification(['reference' => $payment['reference'], 'amount' => 10], 'succeeded');
        $r = $wh->request('POST', '/webhooks/paiement/demo', [], $mismatch['headers'], $mismatch['body']);
        T::eq('amount_mismatch', json_decode($r->body, true)['result'], 'Montant incohérent détecté');
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM audit_log WHERE action = 'payment.amount_mismatch'"), 'Audit du montant incohérent');
        $ok = $wh->request('POST', '/webhooks/paiement/demo', [], $n['headers'], $n['body']);
        T::status(200, $ok, 'Notification valide acceptée (sans CSRF)');
        T::eq('paid', json_decode($ok->body, true)['result'], 'Commande payée');
        $replay = $wh->request('POST', '/webhooks/paiement/demo', [], $n['headers'], $n['body']);
        T::eq('duplicate', json_decode($replay->body, true)['result'], 'Rejeu ignoré');
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM order_status_history WHERE to_status = 'paid' AND order_id = ?", [(int) $order['id']]), 'Un seul passage à « payée »');
        $other = $gw->buildNotification($payment, 'failed');
        T::eq('already_processed', json_decode($wh->request('POST', '/webhooks/paiement/demo', [], $other['headers'], $other['body'])->body, true)['result'], 'Paiement déjà traité');
        T::status(404, $wh->request('POST', '/webhooks/paiement/orange_money', [], [], '{}'), 'Prestataire non intégré → 404');
        $ghost = $gw->buildNotification(['reference' => 'PAY-000000000000', 'amount' => 1], 'succeeded');
        T::eq('unknown_payment', json_decode($wh->request('POST', '/webhooks/paiement/demo', [], $ghost['headers'], $ghost['body'])->body, true)['result'], 'Paiement inconnu');
        // Paiement reçu sur une commande déjà annulée → traitement manuel tracé.
        $c->addToCart('MPCI-CIRE-250', 1);
        [$n2, $t2] = Client::orderRef($c->checkout('Abidjan', 'demo'));
        $o2 = OrderService::findByNumber($n2);
        $p2 = PaymentService::initiate($o2, $t2)['payment'];
        OrderService::transition((int) $o2['id'], 'cancelled', null, 'test');
        DB::run("UPDATE payments SET status = 'initiated' WHERE id = ?", [(int) $p2['id']]);
        $late = $gw->buildNotification($p2, 'succeeded');
        T::eq('paid_closed_order', json_decode($wh->request('POST', '/webhooks/paiement/demo', [], $late['headers'], $late['body'])->body, true)['result'], 'Paiement tardif signalé');
        T::eq('cancelled', OrderService::findByNumber($n2)['status'], 'Commande annulée non réactivée automatiquement');
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM audit_log WHERE action = 'payment.received_on_closed_order'"), 'Audit pour traitement manuel');
    },

    '10 Compte : inscription, connexion, limitation, récupération du mot de passe' => function (): void {
        $c = new Client();
        $r = $c->post('/compte/inscription', T::spamOk() + ['first_name' => 'Yao', 'last_name' => 'Kouassi', 'email' => 'Yao@Example.test', 'phone' => '',
            'password' => 'court', 'password_confirmation' => 'court', 'consent' => '1']);
        T::status(422, $r, 'Mot de passe trop court refusé');
        $r = $c->post('/compte/inscription', T::spamOk() + ['first_name' => 'Yao', 'last_name' => 'Kouassi', 'email' => 'Yao@Example.test', 'phone' => '',
            'password' => 'Abeille2026x', 'password_confirmation' => 'Abeille2026x', 'consent' => '1']);
        T::redirectsTo('/compte', $r, 'Inscription');
        $u = DB::one("SELECT * FROM users WHERE email = 'yao@example.test'");
        T::ok($u !== null && str_starts_with($u['password_hash'], '$2y$') || str_starts_with((string) $u['password_hash'], '$argon'), 'Mot de passe haché');
        $c->post('/compte/deconnexion');
        $dup = $c->post('/compte/inscription', T::spamOk() + ['first_name' => 'Y', 'last_name' => 'K', 'email' => 'yao@example.test', 'phone' => '',
            'password' => 'Abeille2026x', 'password_confirmation' => 'Abeille2026x', 'consent' => '1']);
        T::status(422, $dup, 'E-mail déjà utilisé');
        T::status(422, $c->post('/compte/connexion', ['email' => 'yao@example.test', 'password' => 'mauvais']), 'Mauvais mot de passe');
        T::redirectsTo('/compte', $c->post('/compte/connexion', ['email' => 'yao@example.test', 'password' => 'Abeille2026x']), 'Bon mot de passe');
        $c->post('/compte/deconnexion');
        $attacker = new Client('66.66.66.66');
        for ($i = 0; $i < 5; $i++) {
            $attacker->post('/compte/connexion', ['email' => 'yao@example.test', 'password' => 'essai' . $i]);
        }
        $locked = $attacker->post('/compte/connexion', ['email' => 'yao@example.test', 'password' => 'Abeille2026x']);
        T::status(422, $locked, 'Compte verrouillé après 5 échecs (même avec le bon mot de passe)');
        T::contains('Trop de tentatives', html_entity_decode($locked->body), 'Message de limitation');
        T::redirectsTo('/compte', (new Client('10.1.1.1'))->post('/compte/connexion', ['email' => 'yao@example.test', 'password' => 'Abeille2026x']), 'Autre IP non bloquée');
        // Récupération.
        Mailer::$sent = [];
        $f = $c->post('/compte/mot-de-passe-oublie', ['email' => 'inconnu@example.test']);
        T::contains('Si un compte correspond', $f->body, 'Réponse neutre (pas d\'énumération)');
        T::eq(0, count(Mailer::$sent), 'Aucun e-mail pour un compte inexistant');
        $c->post('/compte/mot-de-passe-oublie', ['email' => 'yao@example.test']);
        T::eq(1, count(Mailer::$sent), 'E-mail de réinitialisation');
        preg_match('#/compte/reinitialiser/([a-f0-9]{64})#', Mailer::$sent[0]['body'], $m);
        T::ok(isset($m[1]), 'Lien de réinitialisation présent');
        T::eq(0, (int) DB::value('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$m[1]]), 'Jeton non stocké en clair');
        T::status(200, $c->get('/compte/reinitialiser/' . $m[1]), 'Formulaire de réinitialisation');
        T::redirectsTo('/compte/connexion', $c->post('/compte/reinitialiser/' . $m[1], ['password' => 'NouveauMiel42', 'password_confirmation' => 'NouveauMiel42']), 'Mot de passe changé');
        T::status(410, $c->post('/compte/reinitialiser/' . $m[1], ['password' => 'AutreMiel4242', 'password_confirmation' => 'AutreMiel4242']), 'Jeton à usage unique');
        T::redirectsTo('/compte', (new Client('10.2.2.2'))->post('/compte/connexion', ['email' => 'yao@example.test', 'password' => 'NouveauMiel42']), 'Connexion avec le nouveau mot de passe');
        T::status(410, $c->get('/compte/reinitialiser/' . str_repeat('a', 64)), 'Jeton inconnu');
    },

    '11 Protection de l\'administration' => function (): void {
        $anon = new Client();
        T::redirectsTo('/admin/connexion', $anon->get('/admin'), 'Anonyme → connexion');
        T::redirectsTo('/admin/connexion', $anon->get('/admin/commandes'), 'Anonyme → connexion (section)');
        T::status(403, $anon->post('/admin/produits', ['name' => 'X']), 'POST anonyme refusé');
        $customer = T::user('customer', 'client@example.test');
        $cc = new Client();
        $cc->loginAs($customer);
        T::status(403, $cc->get('/admin'), 'Client connecté : accès refusé (pas de formulaire de connexion de l\'équipe)');
        T::status(403, $cc->get('/admin/produits'), 'Client refusé (catalogue)');
        T::status(403, $cc->post('/admin/connexion', ['email' => 'client@example.test', 'password' => 'Motdepasse123']), 'Client connecté : connexion d\'équipe refusée');
        $r = (new Client('10.1.1.9'))->post('/admin/connexion', ['email' => 'client@example.test', 'password' => 'Motdepasse123']);
        T::status(422, $r, 'Un client ne peut pas se connecter à l\'admin');
        $admin = T::user('admin', 'admin@example.test');
        $a = new Client();
        T::redirectsTo('/admin', $a->post('/admin/connexion', ['email' => 'admin@example.test', 'password' => 'Motdepasse123']), 'Connexion admin');
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM audit_log WHERE action = 'admin.login'"), 'Connexion journalisée');
        $noCsrf = $a->post('/admin/categories', ['name' => 'Sans CSRF'], false);
        T::status(403, $noCsrf, 'Formulaire sans jeton CSRF → 403 (code standard : Apache remplace 419 par 500)');
        T::contains('Formulaire expiré', $noCsrf->body, 'Page dédiée au formulaire expiré');
        T::status(403, $a->request('POST', '/admin/categories', ['name' => 'Faux', '_csrf' => str_repeat('0', 64)]), 'Jeton CSRF erroné → 403');
        $a->session['staff_seen'] = time() - 7201;
        T::redirectsTo('/admin/connexion', $a->get('/admin'), 'Déconnexion après 2 h d\'inactivité');
        T::ok(empty($a->session['user_id']), 'Session personnel fermée');
        $r = (new Client())->get('/admin/connexion');
        T::contains('noindex', $r->headers['X-Robots-Tag'] ?? '', 'Admin non indexée');
    },

    '12 Permissions par rôle (contrôle serveur)' => function (): void {
        $matrix = [
            'catalog_manager' => ['/admin' => 200, '/admin/produits' => 200, '/admin/stock' => 200, '/admin/promotions' => 200, '/admin/commandes' => 403, '/admin/clients' => 403, '/admin/reglages' => 403, '/admin/equipe' => 403, '/admin/journal' => 403, '/admin/pages' => 403],
            'order_manager' => ['/admin' => 200, '/admin/commandes' => 200, '/admin/devis' => 200, '/admin/clients' => 200, '/admin/produits' => 403, '/admin/stock' => 403, '/admin/reglages' => 403, '/admin/equipe' => 403, '/admin/avis' => 403],
            'admin' => ['/admin/produits' => 200, '/admin/commandes' => 200, '/admin/reglages' => 200, '/admin/equipe' => 200, '/admin/journal' => 200, '/admin/integrations' => 200, '/admin/avis' => 200],
        ];
        foreach ($matrix as $role => $routes) {
            $c = new Client();
            $c->loginAs(T::user($role, "$role@example.test"));
            foreach ($routes as $path => $code) {
                T::status($code, $c->get($path), "$role GET $path");
            }
        }
        $cm = new Client();
        $cm->loginAs(DB::one("SELECT * FROM users WHERE email = 'catalog_manager@example.test'"));
        T::status(403, $cm->post('/admin/commandes/1/statut', ['status' => 'cancelled']), 'Action POST interdite au gestionnaire catalogue');
        T::status(403, $cm->post('/admin/reglages', ['cod_enabled' => '0']), 'Réglages interdits');
        T::eq('1', \App\Services\Settings::get('cod_enabled'), 'Réglage inchangé');
        $om = new Client();
        $om->loginAs(DB::one("SELECT * FROM users WHERE email = 'order_manager@example.test'"));
        T::notContains('/admin/produits', $om->get('/admin')->body, 'Menu filtré selon le rôle');
        // L'administrateur ne peut pas se retirer ses droits ni supprimer le dernier admin.
        $admin = DB::one("SELECT * FROM users WHERE email = 'admin@example.test'");
        $a = new Client();
        $a->loginAs($admin);
        $a->post('/admin/equipe/' . $admin['id'], ['role' => 'order_manager', 'is_active' => '1']);
        T::eq('admin', DB::value('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [(int) $admin['id']]), 'Auto-rétrogradation refusée');
        $a->post('/admin/equipe/' . DB::value("SELECT id FROM users WHERE email = 'order_manager@example.test'"), ['role' => 'catalog_manager', 'is_active' => '1']);
        T::eq('catalog_manager', DB::value("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = 'order_manager@example.test'"), 'Changement de rôle par l\'admin');
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM audit_log WHERE action = 'user.update'"), 'Changement de rôle journalisé');
    },

    '13 CRUD produit (administration)' => function (): void {
        $c = new Client();
        $c->loginAs(T::user('catalog_manager', 'cat@example.test'));
        $r = $c->post('/admin/produits', ['name' => 'Miel de néré', 'slug' => '', 'category_id' => '1', 'short_description' => 'Test', 'description' => "Para 1\n\nPara 2", 'is_active' => '1']);
        T::redirectsTo('/admin/produits/', $r, 'Création');
        $p = DB::one("SELECT * FROM products WHERE slug = 'miel-de-nere'");
        T::ok($p !== null, 'Slug généré');
        T::status(422, $c->post('/admin/produits', ['name' => 'Doublon', 'slug' => 'miel-de-nere']), 'Slug en double refusé');
        T::status(404, (new Client())->get('/boutique/miel-de-nere'), 'Sans variante : non visible');
        $c->post('/admin/produits/' . $p['id'] . '/variantes', ['label' => '500 g', 'format' => 'pot-verre', 'sku' => 'nere-500', 'price' => '5200', 'stock' => '7', 'sort_order' => '0', 'is_active' => '1']);
        $v = T::variant('NERE-500');
        T::eq(7, (int) $v['stock'], 'Variante créée avec stock');
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM stock_movements WHERE variant_id = ? AND reason = 'initial'", [(int) $v['id']]), 'Mouvement initial');
        $c->post('/admin/produits/' . $p['id'] . '/variantes', ['label' => 'x', 'format' => 'pot-verre', 'sku' => 'NERE-500', 'price' => '1', 'stock' => '1', 'is_active' => '1']);
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM product_variants WHERE sku LIKE 'NERE-500%'"), 'SKU en double refusé');
        $public = (new Client())->get('/boutique/miel-de-nere');
        T::status(200, $public, 'Visible avec une variante active');
        T::contains('5 200', html_entity_decode($public->body), 'Prix affiché');
        $c->post('/admin/variantes/' . $v['id'], ['label' => '500 g', 'format' => 'pot-verre', 'sku' => 'NERE-500', 'price' => '5500', 'sort_order' => '0', 'is_active' => '1']);
        T::eq(5500, (int) T::variant('NERE-500')['price'], 'Prix modifié');
        T::eq(7, T::stock('NERE-500'), 'Le stock ne change pas via la fiche variante');
        $c->post('/admin/produits/' . $p['id'], ['name' => 'Miel de néré (édition)', 'slug' => 'miel-de-nere', 'category_id' => '1', 'is_active' => '1']);
        T::eq('Miel de néré (édition)', DB::value('SELECT name FROM products WHERE id = ?', [(int) $p['id']]), 'Mise à jour');
        // Commande, puis suppression : la commande garde son instantané.
        $buyer = new Client('10.3.3.3');
        $buyer->addToCart('NERE-500', 1);
        [$number] = Client::orderRef($buyer->checkout('Abidjan', 'cod'));
        $c->post('/admin/produits/' . $p['id'] . '/supprimer');
        T::status(404, (new Client())->get('/boutique/miel-de-nere'), 'Produit supprimé invisible');
        T::ok(DB::value('SELECT deleted_at FROM products WHERE id = ?', [(int) $p['id']]) !== null, 'Suppression logique');
        T::eq('Miel de néré (édition)', DB::value('SELECT i.product_name FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.number = ?', [$number]), 'Ligne de commande intacte');
        T::ok((int) DB::value("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'product.%' OR action LIKE 'variant.%'") >= 5, 'Actions catalogue journalisées');
        // Image : un faux fichier image est refusé.
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, '<?php echo "x"; ?>');
        $res = \App\Services\ImageService::store(['error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'tmp_name' => $tmp, 'name' => 'a.jpg']);
        T::ok(!$res['ok'], 'Fichier PHP déguisé refusé');
        $png = tempnam(sys_get_temp_dir(), 'img');
        $im = imagecreatetruecolor(40, 30);
        imagepng($im, $png);
        $res = \App\Services\ImageService::store(['error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'tmp_name' => $png, 'name' => 'evil.php.png'], 'tests');
        T::ok($res['ok'] && str_ends_with($res['path'], '.webp'), 'Image ré-encodée en WebP avec nom aléatoire');
        \App\Services\ImageService::delete($res['path']);
        @rmdir(BASE_PATH . '/public/uploads/tests');
    },

    '14 Changements de statut de commande' => function (): void {
        $c = new Client();
        $c->addToCart('MPCI-LIQ-500', 2);
        $c->post('/panier/code-promo', ['code' => 'BIENVENUE10']);
        [$number] = Client::orderRef($c->checkout('Abidjan', 'cod'));
        $o = OrderService::findByNumber($number);
        T::eq(1, (int) DB::value("SELECT used_count FROM promo_codes WHERE code = 'BIENVENUE10'"), 'Promo consommée');
        $a = new Client();
        $a->loginAs(T::user('order_manager', 'om@example.test'));
        $a->post('/admin/commandes/' . $o['id'] . '/statut', ['status' => 'shipped']);
        T::eq('pending_confirmation', OrderService::find((int) $o['id'])['status'], 'Transition interdite (confirmation → expédiée)');
        $a->post('/admin/commandes/' . $o['id'] . '/statut', ['status' => 'refunded']);
        T::eq('pending_confirmation', OrderService::find((int) $o['id'])['status'], 'Remboursement impossible sans paiement');
        foreach (['preparing', 'shipped', 'delivered'] as $s) {
            $a->post('/admin/commandes/' . $o['id'] . '/statut', ['status' => $s, 'note' => "→ $s"]);
            T::eq($s, OrderService::find((int) $o['id'])['status'], "Transition vers $s");
        }
        $o = OrderService::find((int) $o['id']);
        T::eq('paid', $o['payment_status'], 'Livraison COD = paiement encaissé');
        $a->post('/admin/commandes/' . $o['id'] . '/statut', ['status' => 'refunded']);
        T::eq('refunded', OrderService::find((int) $o['id'])['payment_status'], 'Remboursement après paiement');
        T::eq(4, (int) DB::value("SELECT COUNT(*) FROM audit_log WHERE action = 'order.status'"), 'Transitions journalisées');
        T::ok(count(array_filter(Mailer::$sent, static fn ($m) => str_contains($m['subject'], 'Expédiée'))) === 1, 'Client notifié de l\'expédition');
        // Annulation : stock et promo restitués, une seule fois.
        $c->addToCart('MPCI-LIQ-500', 2);
        $c->post('/panier/code-promo', ['code' => 'BIENVENUE10']);
        [$n2] = Client::orderRef($c->checkout('Abidjan', 'cod'));
        $o2 = OrderService::findByNumber($n2);
        $stock = T::stock('MPCI-LIQ-500');
        $a->post('/admin/commandes/' . $o2['id'] . '/statut', ['status' => 'cancelled']);
        T::eq($stock + 2, T::stock('MPCI-LIQ-500'), 'Stock restitué');
        T::eq(1, (int) DB::value("SELECT used_count FROM promo_codes WHERE code = 'BIENVENUE10'"), 'Compteur promo restitué');
        try {
            OrderService::transition((int) $o2['id'], 'cancelled', null);
            T::ok(false, 'Double annulation refusée');
        } catch (\DomainException) {
            T::ok(true, 'Double annulation refusée');
        }
        T::eq($stock + 2, T::stock('MPCI-LIQ-500'), 'Pas de double restitution');
        $a->post('/admin/commandes/' . $o2['id'] . '/note', ['note' => 'Client injoignable']);
        $page = $c->get('/commande/' . $n2 . '?t=' . ((array) $c->session['order_tokens'])[$n2]);
        T::notContains('Client injoignable', $page->body, 'Note interne invisible du client');
    },

    '15 Rendu mobile et bureau (contrôles structurels)' => function (): void {
        $c = new Client();
        $css = (string) file_get_contents(BASE_PATH . '/public/assets/css/app.css');
        foreach (['@media (min-width: 640px)', '@media (min-width: 900px)', '@media (min-width: 960px)', 'prefers-color-scheme: dark', ':root[data-theme="dark"]', 'prefers-reduced-motion', ':focus-visible', 'env(safe-area-inset-bottom)'] as $needle) {
            T::contains($needle, $css, 'CSS responsive/accessibilité');
        }
        foreach (['/', '/boutique', '/boutique/miel-liquide-toutes-fleurs', '/panier', '/contact', '/faq', '/commandes-professionnelles', '/compte/connexion', '/a-propos', '/mentions-legales'] as $path) {
            $r = $c->get($path);
            T::status(200, $r, "GET $path");
            T::contains('content="width=device-width, initial-scale=1', $r->body, "Viewport $path");
            T::contains('<html lang="fr"', $r->body, "Langue $path");
            T::contains('class="skip-link"', $r->body, "Lien d'évitement $path");
            T::ok(preg_match('/<script(?![^>]*\bsrc=)(?![^>]*application\/ld\+json)[^>]*>/i', $r->body) === 0, "Aucun script inline $path");
            T::ok(preg_match('/\sstyle="/i', $r->body) === 0, "Aucun style inline $path");
            T::ok(preg_match('/\son[a-z]+="/i', $r->body) === 0, "Aucun gestionnaire d'événement inline $path");
            T::eq(1, preg_match_all('/<h1[\s>]/', $r->body), "Un seul h1 sur $path");
            preg_match_all('/<img\b[^>]*>/i', $r->body, $imgs);
            foreach ($imgs[0] as $img) {
                T::ok(str_contains($img, 'alt='), "Attribut alt sur $path");
            }
            $csp = $r->headers['Content-Security-Policy'] ?? '';
            T::contains("script-src 'self'", $csp, "CSP $path");
            T::eq('nosniff', $r->headers['X-Content-Type-Options'] ?? '', "nosniff $path");
            T::eq('DENY', $r->headers['X-Frame-Options'] ?? '', "Anti-clickjacking $path");
        }
        T::status(200, $c->get('/sitemap.xml'), 'Sitemap');
        T::contains('<loc>http://localhost/boutique/miel-liquide-toutes-fleurs</loc>', $c->get('/sitemap.xml')->body, 'Produit dans le sitemap');
        $robots = $c->get('/robots.txt')->body;
        T::contains('Disallow: /admin', $robots, 'robots.txt');
        T::notContains("Disallow: /commande\n", $robots, '/commandes-professionnelles reste indexable');
    },

    '16 Formulaires : validation, erreurs accessibles, anti-spam' => function (): void {
        $c = new Client();
        $r = $c->post('/contact', T::spamOk() + ['name' => '', 'email' => 'pas-un-email', 'subject' => '', 'message' => 'court']);
        T::status(422, $r, 'Contact invalide');
        T::contains('error-summary', $r->body, 'Résumé des erreurs');
        T::contains('aria-invalid="true"', $r->body, 'Champs marqués invalides');
        T::contains('Adresse e-mail invalide', $r->body, 'Message e-mail');
        T::contains('value="pas-un-email"', $r->body, 'Saisie conservée');
        $valid = ['name' => 'Aminata', 'email' => 'aminata@example.test', 'phone' => '', 'subject' => 'Question', 'message' => 'Bonjour, livrez-vous à Korhogo ?', 'consent' => '1'];
        T::redirectsTo('/contact', $c->post('/contact', T::spamOk() + $valid), 'Contact valide');
        T::eq(1, (int) DB::value('SELECT COUNT(*) FROM contact_messages'), 'Message enregistré');
        T::status(422, $c->post('/contact', ['website' => 'http://spam'] + T::spamOk() + $valid), 'Champ piège rempli refusé');
        Env::set('ANTISPAM_MIN_SECONDS', '3');
        $ts = (string) time();
        T::status(422, $c->post('/contact', ['_ts' => $ts . '.' . hash_hmac('sha256', 'form|' . $ts, app_key())] + $valid), 'Envoi trop rapide refusé');
        T::status(422, $c->post('/contact', ['_ts' => $ts . '.forge'] + $valid), 'Horodatage forgé refusé');
        Env::set('ANTISPAM_MIN_SECONDS', '0');
        T::eq(1, (int) DB::value('SELECT COUNT(*) FROM contact_messages'), 'Aucun spam enregistré');
        $pro = ['company' => 'Hôtel Test', 'contact_name' => 'K. Traoré', 'email' => 'hotel@example.test', 'phone' => '0102030405', 'activity' => 'hotel', 'consent' => '1'];
        T::status(422, $c->post('/commandes-professionnelles', T::spamOk() + ['activity' => 'pirate'] + $pro), 'Activité hors liste refusée');
        T::redirectsTo('/commandes-professionnelles', $c->post('/commandes-professionnelles', T::spamOk() + $pro), 'Devis valide');
        $c->addToCart('MPCI-LIQ-250', 1);
        $bad = $c->checkout('Abidjan', 'cod', ['first_name' => '', 'phone' => 'abc', 'cgv' => '']);
        T::status(422, $bad, 'Commande incomplète refusée');
        T::contains('Le prénom est obligatoire', $bad->body, 'Erreur prénom');
        T::contains('Numéro de téléphone invalide', $bad->body, 'Erreur téléphone');
        T::contains('Vous devez accepter', $bad->body, 'CGV obligatoires');
        $xss = $c->post('/contact', T::spamOk() + ['name' => '<script>alert(1)</script>', 'email' => 'x', 'subject' => 's', 'message' => 'm']);
        T::notContains('<script>alert(1)</script>', $xss->body, 'Saisie échappée (XSS)');
        T::contains('&lt;script&gt;alert(1)&lt;/script&gt;', $xss->body, 'Saisie restituée échappée');
        $c->post('/newsletter', ['email' => 'lettre@example.test']);
        T::eq(0, (int) DB::value('SELECT COUNT(*) FROM newsletter_subscribers'), 'Newsletter sans consentement refusée');
        $c->post('/newsletter', ['email' => 'lettre@example.test', 'consent' => '1']);
        T::eq(1, (int) DB::value('SELECT COUNT(*) FROM newsletter_subscribers'), 'Newsletter avec consentement');
        T::redirectsTo('/', $c->post('/newsletter', ['email' => 'x@example.test', 'consent' => '1', 'retour' => '//evil.example']), 'Redirection externe neutralisée');
    },

    '17 Cohérence du stock' => function (): void {
        $a = new Client('10.0.0.10');
        $b = new Client('10.0.0.11');
        $a->addToCart('MPCI-CRE-500', 3);
        $b->addToCart('MPCI-CRE-500', 2);
        [$na] = Client::orderRef($a->checkout('Bouaké', 'cod'));
        T::eq(0, T::stock('MPCI-CRE-500'), 'Dernières unités vendues à A');
        $rb = $b->get('/commande');
        T::redirectsTo('/panier', $rb, 'B ne peut pas commander');
        $direct = OrderService::create(['first_name' => 'B', 'last_name' => 'B', 'email' => 'b@example.test', 'phone' => '0700000000', 'zone_id' => T::zone('Bouaké')['id'],
            'city' => 'Bouaké', 'district' => '', 'address' => 'x', 'landmark' => '', 'note' => '', 'payment_method' => 'cod'],
            \App\Services\CartService::current(new \App\Core\Request('GET', '/', [], [], $b->cookies)), null, OrderService::newIdempotencyKey(), 11000 + 1500);
        T::eq('error', $direct['status'], 'Création refusée même en contournant le panier');
        T::eq(0, T::stock('MPCI-CRE-500'), 'Jamais de stock négatif');
        T::eq(1, (int) DB::value('SELECT COUNT(*) FROM orders'), 'Aucune commande pour B');
        // Ajustement admin avec verrou optimiste.
        $admin = new Client();
        $admin->loginAs(T::user('catalog_manager', 'stock@example.test'));
        $v = T::variant('MPCI-CRE-500');
        $admin->post('/admin/stock/' . $v['id'], ['stock' => '10', 'current' => '5', 'note' => 'Inventaire']);
        T::eq(0, T::stock('MPCI-CRE-500'), 'Ajustement refusé si la valeur de référence a changé');
        $admin->post('/admin/stock/' . $v['id'], ['stock' => '10', 'current' => '0', 'note' => 'Réception']);
        T::eq(10, T::stock('MPCI-CRE-500'), 'Ajustement accepté');
        $admin->post('/admin/stock/' . $v['id'], ['stock' => '-4', 'current' => '10', 'note' => 'x']);
        T::eq(10, T::stock('MPCI-CRE-500'), 'Stock négatif refusé');
        OrderService::transition((int) OrderService::findByNumber($na)['id'], 'cancelled', null);
        T::eq(13, T::stock('MPCI-CRE-500'), 'Annulation restitue 3');
        // Invariant : somme des mouvements = stock, pour chaque variante.
        foreach (DB::all('SELECT v.sku, v.stock, (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m WHERE m.variant_id = v.id) AS mv FROM product_variants v') as $row) {
            T::eq((int) $row['stock'], (int) $row['mv'], 'Mouvements = stock pour ' . $row['sku']);
        }
        try {
            DB::run("UPDATE product_variants SET stock = -1 WHERE sku = 'MPCI-CRE-500'");
            T::ok(false, 'La base refuse un stock négatif');
        } catch (\PDOException) {
            T::ok(true, 'La base refuse un stock négatif (CHECK)');
        }
    },

    '18 Confidentialité des données client' => function (): void {
        $owner = new Client('10.0.1.1');
        $user = T::user('customer', 'proprio@example.test');
        $owner->loginAs($user);
        $owner->addToCart('MPCI-LIQ-250', 1);
        [$number, $token] = Client::orderRef($owner->checkout('Abidjan', 'cod', ['address' => 'Villa 12, rue secrète', 'email' => 'proprio@example.test']));
        $stranger = new Client('10.0.1.2');
        T::status(404, $stranger->get("/commande/$number"), 'Sans jeton → 404');
        T::status(404, $stranger->get("/commande/$number?t=" . str_repeat('a', 32)), 'Mauvais jeton → 404');
        T::status(404, $stranger->get("/commande/$number/recu"), 'Récapitulatif protégé');
        T::status(200, $stranger->get("/commande/$number?t=$token"), 'Lien secret valide');
        $other = new Client('10.0.1.3');
        $other->loginAs(T::user('customer', 'autre@example.test'));
        T::status(404, $other->get("/commande/$number"), 'Autre client → 404');
        T::notContains($number, $other->get('/compte/commandes')->body, 'Pas dans la liste d\'un autre client');
        T::status(200, $owner->get("/commande/$number"), 'Propriétaire connecté');
        $owner->post('/compte/adresses', ['label' => 'Maison', 'recipient_name' => 'P', 'phone' => '0700000000', 'city' => 'Abidjan', 'address_line' => 'x']);
        $addrId = (int) DB::value('SELECT id FROM addresses WHERE user_id = ?', [(int) $user['id']]);
        T::status(404, $other->post("/compte/adresses/$addrId/supprimer"), 'Suppression d\'adresse d\'autrui impossible');
        $track = $stranger->post('/suivi-commande', ['number' => $number, 'email' => 'mauvais@example.test']);
        T::status(404, $track, 'Suivi : mauvais e-mail');
        $track = $stranger->post('/suivi-commande', ['number' => $number, 'email' => 'PROPRIO@example.test']);
        T::status(200, $track, 'Suivi : bon e-mail');
        T::notContains('rue secrète', $track->body, 'Suivi : adresse non divulguée');
        $page = $stranger->get("/commande/$number?t=$token");
        T::contains('no-store', $page->headers['Cache-Control'] ?? '', 'Pas de mise en cache');
        T::contains('noindex', $page->headers['X-Robots-Tag'] ?? '', 'Pas d\'indexation');
        T::status(403, (function () {
            $c = new Client();
            $c->loginAs(T::user('catalog_manager', 'cm@example.test'));
            return $c->get('/admin/clients');
        })(), 'Clients invisibles sans permission');
        // Export CSV protégé contre l'injection de formules.
        $evil = new Client('10.0.1.4');
        $evil->addToCart('MPCI-LIQ-250', 1);
        $evil->checkout('Abidjan', 'cod', ['first_name' => '=HYPERLINK("http://evil")', 'last_name' => '+cmd']);
        $adm = new Client();
        $adm->loginAs(T::user('admin', 'adm@example.test'));
        $csv = $adm->get('/admin/commandes/export');
        T::contains('text/csv', $csv->headers['Content-Type'] ?? '', 'Export CSV');
        T::contains("'=HYPERLINK", $csv->body, 'Formule neutralisée');
        T::eq(1, (int) DB::value("SELECT COUNT(*) FROM audit_log WHERE action = 'orders.export'"), 'Export journalisé');
        $columns = DB::driver() === 'sqlite'
            ? array_merge(...array_map(static fn ($t) => array_column(DB::all("PRAGMA table_info({$t['name']})"), 'name'), DB::all("SELECT name FROM sqlite_master WHERE type = 'table'")))
            : array_column(DB::all('SELECT column_name AS name FROM information_schema.columns WHERE table_schema = DATABASE()'), 'name');
        T::eq([], array_values(array_filter($columns, static fn ($c) => preg_match('/(^|_)(card|cvv|cvc|pan|iban)(_|$)/i', (string) $c) === 1)), 'Aucune colonne de données bancaires');
    },

    '19 Sécurité transverse : en-têtes, redirections, méthodes, Auth' => function (): void {
        $c = new Client();
        T::status(405, $c->request('DELETE', '/panier'), 'Méthode non autorisée');
        T::status(404, $c->get('/route-inexistante'), '404 propre');
        T::user('customer', 'redir@example.test');
        $r = $c->post('/compte/connexion', ['email' => 'redir@example.test', 'password' => 'Motdepasse123', 'retour' => 'https://evil.example/vol']);
        T::eq('/compte', $r->headers['Location'] ?? '', 'Paramètre « retour » externe ignoré (pas de redirection ouverte)');
        T::eq('/', safe_return('//evil.example/x'), 'safe_return refuse //');
        T::eq('/', safe_return('https://evil.example'), 'safe_return refuse une URL absolue');
        T::eq('/panier', safe_return('/panier'), 'safe_return accepte un chemin interne');
        T::eq('/', \App\Core\Response::redirect('https://evil.example')->headers['Location'], 'Response::redirect neutralise l\'externe');
        T::eq("'=1+1", csv_safe('=1+1'), 'csv_safe');
        T::eq('&lt;b&gt;', e('<b>'), 'Échappement HTML');
        T::notContains('<script>', rich("## Titre\n\n<script>alert(1)</script> **gras**"), 'rich() n\'interprète pas le HTML');
        T::contains('<mark class="todo">[À CONFIRMER]</mark>', rich_inline('Texte [À CONFIRMER]'), 'Marqueurs provisoires surlignés');
        Auth::reset();
    },

    // ===================== Tests de sécurité dédiés =====================

    '20 Sécurité : injection SQL (public, compte, administration)' => function (): void {
        $payloads = ["' OR '1'='1", "1; DROP TABLE users--", '" OR 1=1--', "') UNION SELECT password_hash, email FROM users--", "%' OR 1=1 #", "1 AND SLEEP(3)", "1' AND (SELECT 1 FROM (SELECT SLEEP(3))x)--", '\\', "'; DELETE FROM products; --"];
        $users = (int) DB::value('SELECT COUNT(*) FROM users');
        $c = new Client();
        $admin = new Client('10.5.5.5');
        $admin->loginAs(T::user('admin', 'sqli-admin@example.test'));
        $t0 = microtime(true);
        foreach ($payloads as $p) {
            $q = rawurlencode($p);
            foreach (["/boutique?q=$q", "/boutique?categorie=$q", "/boutique?tri=$q", "/boutique?format=$q", "/boutique?prix_min=$q&prix_max=$q", "/boutique?page=$q", '/commande/estimation?zone_id=' . $q] as $url) {
                $r = $c->get($url);
                T::ok($r->status < 500 && !str_contains($r->body, 'SQLSTATE'), "Public $url → " . $r->status);
            }
            foreach (["/admin/commandes?q=$q&statut=$q&du=$q&au=$q&paiement=$q", "/admin/clients?q=$q", "/admin/produits?q=$q&statut=$q", "/admin/journal?action=$q", "/admin/commandes/export?q=$q", "/admin/devis?statut=$q", "/admin/avis?statut=$q"] as $url) {
                $r = $admin->get($url);
                T::ok($r->status < 500 && !str_contains($r->body, 'SQLSTATE'), "Admin $url → " . $r->status);
            }
            T::ok($c->post('/suivi-commande', ['number' => $p, 'email' => 'a@b.ci'])->status < 500, 'Suivi');
            T::eq(422, (new Client('10.6.' . random_int(0, 250) . '.1'))->post('/compte/connexion', ['email' => $p, 'password' => $p])->status, 'Connexion avec charge SQL');
            T::ok($c->post('/panier/code-promo', ['code' => $p])->status < 500, 'Code promo');
            T::ok($c->post('/panier/ajouter', ['variant_id' => $p, 'qty' => $p])->status < 500, 'Ajout panier');
        }
        T::ok(microtime(true) - $t0 < 20, 'Aucune temporisation SLEEP exécutée');
        T::status(404, $c->get("/boutique/'%20OR%201=1"), 'Slug hors motif rejeté par le routeur');
        T::status(404, $admin->get('/admin/commandes/1%20OR%201=1'), 'Identifiant non numérique rejeté');
        T::eq($users + 1, (int) DB::value('SELECT COUNT(*) FROM users'), 'Table users intacte');
        T::eq(5, (int) DB::value('SELECT COUNT(*) FROM products'), 'Table products intacte');
        T::contains('0 produit', $c->get('/boutique?q=' . rawurlencode("' OR '1'='1"))->body, 'La charge est cherchée comme texte, pas exécutée');
    },

    '21 Sécurité : XSS réfléchi et stocké (public et administration)' => function (): void {
        $x = '"><script>alert(1)</script><img src=x onerror=alert(2)>';
        $raw = ['<script>alert(1)</script>', '<img src=x onerror=alert(2)>'];
        $clean = static function (string $body, string $where) use ($raw): void {
            foreach ($raw as $needle) {
                T::notContains($needle, $body, "Charge XSS non échappée : $where");
            }
        };
        $c = new Client();
        $clean($c->get('/boutique?q=' . rawurlencode($x))->body, 'recherche');
        $clean($c->get('/compte/connexion?retour=' . rawurlencode($x))->body, 'paramètre retour');
        $clean($c->post('/suivi-commande', ['number' => $x, 'email' => $x])->body, 'suivi');
        // Stocké : avis, contact, devis, commande.
        $c->post('/boutique/miel-cremeux/avis', T::spamOk() + ['author_name' => mb_substr($x, 0, 100), 'rating' => '5', 'comment' => $x . ' texte suffisant']);
        $c->post('/contact', T::spamOk() + ['name' => mb_substr($x, 0, 150), 'email' => 'xss@example.test', 'subject' => mb_substr($x, 0, 160), 'message' => $x, 'consent' => '1']);
        $c->post('/commandes-professionnelles', T::spamOk() + ['company' => $x, 'contact_name' => $x, 'email' => 'xss@example.test', 'phone' => '0102030405', 'activity' => 'hotel', 'message' => $x, 'products_interest' => $x, 'consent' => '1']);
        $c->addToCart('MPCI-LIQ-250', 1);
        [$number, $token] = Client::orderRef($c->checkout('Abidjan', 'cod', ['first_name' => mb_substr($x, 0, 100), 'last_name' => 'X', 'address' => $x, 'landmark' => $x, 'note' => $x]));
        T::ok(DB::value('SELECT COUNT(*) FROM reviews') === 1 || (int) DB::value('SELECT COUNT(*) FROM reviews') === 1, 'Avis enregistré tel quel (échappé à l\'affichage)');
        $clean($c->get("/commande/$number?t=$token")->body, 'page commande client');
        $clean($c->get("/commande/$number/recu?t=$token")->body, 'récapitulatif');
        $a = new Client('10.7.7.7');
        $a->loginAs(T::user('admin', 'xss-admin@example.test'));
        DB::run("UPDATE reviews SET status = 'approved'");
        $clean($c->get('/boutique/miel-cremeux')->body, 'avis publié');
        foreach (['/admin', '/admin/commandes', '/admin/commandes/' . OrderService::findByNumber($number)['id'], '/admin/messages', '/admin/devis', '/admin/avis?statut=approved', '/admin/journal'] as $url) {
            $r = $a->get($url);
            T::status(200, $r, "Admin $url");
            $clean($r->body, $url);
        }
        // Contenus saisis par le personnel : pas d'HTML, pas de lien javascript: ni //domaine.
        $a->post('/admin/contenu', ['home_hero_title' => $x, 'home_hero_text' => "[clic](javascript:alert(1)) [ext](//evil.example) [ok](/boutique) $x", 'home_story_title' => 's', 'home_story_text' => $x, 'home_pro_text' => 'p', 'store_tagline' => 't']);
        $home = $c->get('/')->body;
        $clean($home, 'accueil édité');
        T::notContains('href="javascript:', $home, 'Lien javascript: refusé');
        T::notContains('href="//evil.example', $home, 'Lien //domaine refusé');
        T::contains('href="/boutique"', $home, 'Lien interne accepté');
        T::status(422, $a->post('/admin/reglages', ['social_facebook' => 'javascript:alert(1)']), 'Lien social non https refusé');
        // Nom de produit malveillant : JSON-LD protégé.
        $a->post('/admin/produits/1', ['name' => '</script><script>alert(3)</script>', 'slug' => 'miel-liquide-toutes-fleurs', 'category_id' => '1', 'is_active' => '1']);
        $page = $c->get('/boutique/miel-liquide-toutes-fleurs')->body;
        T::notContains('<script>alert(3)</script>', $page, 'JSON-LD et titre échappés');
        T::contains('\u003C/script\u003E', $page, 'Balise neutralisée dans le JSON-LD');
    },

    '22 Sécurité : sessions, CSRF, invalidation après changement de mot de passe' => function (): void {
        T::user('customer', 'sess@example.test');
        $a = new Client('10.8.0.1');
        $before = $a->csrf();
        $a->post('/compte/connexion', ['email' => 'sess@example.test', 'password' => 'Motdepasse123']);
        T::ok(($a->session['_csrf'] ?? '') !== $before, 'Jeton CSRF renouvelé à la connexion');
        $b = new Client('10.8.0.2');
        $b->post('/compte/connexion', ['email' => 'sess@example.test', 'password' => 'Motdepasse123']);
        T::status(200, $b->get('/compte'), 'Seconde session ouverte');
        $a->post('/compte/mot-de-passe', ['current_password' => 'Motdepasse123', 'password' => 'Changement2026', 'password_confirmation' => 'Changement2026']);
        T::status(200, $a->get('/compte'), 'Session qui change le mot de passe conservée');
        T::redirectsTo('/compte/connexion', $b->get('/compte'), 'Autre session invalidée après changement de mot de passe');
        // Réinitialisation par e-mail : toutes les sessions sont fermées.
        Mailer::$sent = [];
        $a->post('/compte/mot-de-passe-oublie', ['email' => 'sess@example.test']);
        preg_match('#/compte/reinitialiser/([a-f0-9]{64})#', Mailer::$sent[0]['body'], $m);
        (new Client('10.8.0.3'))->post('/compte/reinitialiser/' . $m[1], ['password' => 'Reinit2026xyz', 'password_confirmation' => 'Reinit2026xyz']);
        T::redirectsTo('/compte/connexion', $a->get('/compte'), 'Sessions fermées après réinitialisation');
        // Compte du personnel désactivé : déconnecté immédiatement.
        $staff = T::user('order_manager', 'staff-off@example.test');
        $s = new Client('10.8.0.4');
        $s->loginAs($staff);
        T::status(200, $s->get('/admin'), 'Personnel connecté');
        DB::run('UPDATE users SET is_active = 0 WHERE id = ?', [(int) $staff['id']]);
        T::redirectsTo('/admin/connexion', $s->get('/admin'), 'Compte désactivé → accès coupé');
        // Déconnexion : identité effacée, panier conservé.
        $c = new Client('10.8.0.5');
        $c->addToCart('MPCI-LIQ-250', 1);
        $c->post('/compte/connexion', ['email' => 'sess@example.test', 'password' => 'Reinit2026xyz']);
        $c->post('/compte/deconnexion');
        T::ok(!isset($c->session['user_id']) && !isset($c->session['pwd_fp']), 'Session vidée à la déconnexion');
        T::status(403, $c->post('/compte/deconnexion', [], false), 'Déconnexion sans CSRF refusée (pas de déconnexion forcée)');
        // Session forgée : un user_id sans empreinte est rejeté.
        $forged = new Client('10.8.0.6');
        $forged->session = ['user_id' => (int) DB::value("SELECT id FROM users WHERE email = 'sess@example.test'")];
        T::redirectsTo('/compte/connexion', $forged->get('/compte'), 'Session sans empreinte rejetée');
        // Drapeaux des cookies.
        \App\Core\Cookie::$queued = [];
        \App\Services\CartService::current(new \App\Core\Request('GET', '/'), true);
        T::ok(\App\Core\Cookie::$queued['mpci_cart']['httponly'] === true, 'Cookie panier HttpOnly');
        Env::set('APP_URL', 'https://boutique.example.ci');
        T::ok(\App\Core\Cookie::secure(), 'Cookies Secure en HTTPS');
        T::contains('max-age=31536000', (new Client())->get('/')->headers['Strict-Transport-Security'] ?? '', 'HSTS en HTTPS');
        Env::set('APP_URL', 'http://localhost');
        \App\Core\Cookie::$queued = [];
    },

    '23 Sécurité : contrôle d\'accès (vertical, horizontal) et mass assignment' => function (): void {
        $customer = T::user('customer', 'cust@example.test');
        $cu = new Client('10.9.0.1');
        $cu->loginAs($customer);
        foreach (['/admin/produits' => ['name' => 'X'], '/admin/equipe' => ['email' => 'pirate@example.test', 'role' => 'admin'], '/admin/reglages' => ['cod_enabled' => '0'], '/admin/commandes/1/statut' => ['status' => 'cancelled']] as $url => $data) {
            T::status(403, $cu->post($url, $data), "Client → POST $url refusé");
        }
        T::status(403, $cu->get('/admin/commandes/export'), 'Client → export refusé');
        $om = new Client('10.9.0.2');
        $omUser = T::user('order_manager', 'om2@example.test');
        $om->loginAs($omUser);
        T::status(403, $om->post('/admin/equipe', ['first_name' => 'P', 'last_name' => 'P', 'email' => 'p@example.test', 'role' => 'admin', 'password' => 'Pirate123456']), 'Gestionnaire commandes ne crée pas d\'admin');
        T::status(403, $om->post('/admin/equipe/' . $omUser['id'], ['role' => 'admin', 'is_active' => '1']), 'Pas d\'auto-promotion');
        T::status(403, $om->post('/admin/avis/1', ['status' => 'approved']), 'Pas de modération');
        T::status(403, $om->post('/admin/stock/1', ['stock' => '999', 'current' => '40', 'note' => 'x']), 'Pas d\'ajustement de stock');
        T::status(403, $om->post('/admin/livraison', ['name' => 'Z', 'fee' => '0']), 'Pas de zones');
        T::eq('order_manager', DB::value('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [(int) $omUser['id']]), 'Rôle inchangé');
        T::eq(0, (int) DB::value("SELECT COUNT(*) FROM users WHERE email = 'p@example.test'"), 'Aucun compte créé');
        $cm = new Client('10.9.0.3');
        $cm->loginAs(T::user('catalog_manager', 'cm2@example.test'));
        T::status(403, $cm->get('/admin/commandes/1'), 'Gestionnaire catalogue sans accès aux commandes');
        T::status(403, $cm->post('/admin/contenu', ['home_hero_title' => 'piraté']), 'Pas d\'édition de contenu');
        // Mass assignment : champs non prévus ignorés.
        $cu->post('/compte/profil', ['first_name' => 'Cust', 'last_name' => 'Test', 'phone' => '', 'role_id' => '1', 'is_active' => '0', 'email' => 'vol@example.test', 'password_hash' => 'x']);
        $after = DB::one('SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [(int) $customer['id']]);
        T::eq('customer', $after['role'], 'role_id ignoré');
        T::eq('cust@example.test', $after['email'], 'e-mail non modifiable');
        T::eq(1, (int) $after['is_active'], 'is_active ignoré');
        T::ok(password_verify('Motdepasse123', $after['password_hash']), 'password_hash ignoré');
        $reg = new Client('10.9.0.4');
        $reg->post('/compte/inscription', T::spamOk() + ['first_name' => 'R', 'last_name' => 'R', 'email' => 'reg@example.test', 'password' => 'Inscrit2026x', 'password_confirmation' => 'Inscrit2026x', 'consent' => '1', 'role' => 'admin', 'role_id' => '1']);
        T::eq('customer', DB::value("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = 'reg@example.test'"), 'Inscription toujours en client');
        $cu->addToCart('MPCI-LIQ-250', 1);
        $r = $cu->checkout('Abidjan', 'cod', ['user_id' => '999', 'status' => 'paid', 'payment_status' => 'paid']);
        $o = OrderService::findByNumber(Client::orderRef($r)[0]);
        T::eq((int) $customer['id'], (int) $o['user_id'], 'user_id imposé par la session');
        T::eq('pending_confirmation', $o['status'], 'Statut non injectable');
        T::eq('unpaid', $o['payment_status'], 'État de paiement non injectable');
        T::redirectsTo('/compte/connexion', (new Client())->get('/compte/adresses'), 'Espace client protégé');
    },

    '24 Sécurité : téléversement de fichiers' => function (): void {
        $tmp = static function (string $content): string {
            $f = tempnam(sys_get_temp_dir(), 'up');
            file_put_contents($f, $content);
            return $f;
        };
        $svg = $tmp('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        T::ok(!\App\Services\ImageService::store(['error' => UPLOAD_ERR_OK, 'size' => filesize($svg), 'tmp_name' => $svg, 'name' => 'a.svg'])['ok'], 'SVG refusé');
        $gif = $tmp("GIF89a\x01\x00\x01\x00\x00\x00\x00;<?php system(\$_GET['c']); ?>");
        T::ok(!\App\Services\ImageService::store(['error' => UPLOAD_ERR_OK, 'size' => filesize($gif), 'tmp_name' => $gif, 'name' => 'a.gif'])['ok'], 'GIF polyglotte refusé');
        $html = $tmp('<html><script>alert(1)</script></html>');
        T::ok(!\App\Services\ImageService::store(['error' => UPLOAD_ERR_OK, 'size' => filesize($html), 'tmp_name' => $html, 'name' => 'a.jpg'])['ok'], 'HTML déguisé en .jpg refusé');
        T::ok(!\App\Services\ImageService::store(['error' => UPLOAD_ERR_OK, 'size' => 6 * 1024 * 1024, 'tmp_name' => $html, 'name' => 'a.jpg'])['ok'], 'Fichier > 5 Mo refusé');
        T::ok(!\App\Services\ImageService::store(['error' => UPLOAD_ERR_INI_SIZE, 'size' => 0, 'tmp_name' => '', 'name' => 'a.jpg'])['ok'], 'Erreur de téléversement gérée');
        T::ok(!\App\Services\ImageService::store(['error' => [0], 'size' => 1, 'tmp_name' => '', 'name' => 'a.jpg'])['ok'], 'Téléversement multiple malformé refusé');
        // JPEG valide contenant du PHP en commentaire : ré-encodé, la charge disparaît.
        $im = imagecreatetruecolor(20, 20);
        $jpg = tempnam(sys_get_temp_dir(), 'up');
        imagejpeg($im, $jpg);
        file_put_contents($jpg, file_get_contents($jpg) . '<?php echo "pwn"; ?>');
        $res = \App\Services\ImageService::store(['error' => UPLOAD_ERR_OK, 'size' => filesize($jpg), 'tmp_name' => $jpg, 'name' => '../../shell.php.jpg'], 'tests');
        T::ok($res['ok'], 'JPEG valide accepté');
        T::ok(preg_match('#^/uploads/tests/\d{6}-[a-f0-9]{20}\.webp$#', $res['path']) === 1, 'Nom aléatoire, pas de traversée de répertoire');
        T::notContains('<?php', (string) file_get_contents(BASE_PATH . '/public' . $res['path']), 'Charge PHP supprimée par le ré-encodage');
        \App\Services\ImageService::delete($res['path']);
        @rmdir(BASE_PATH . '/public/uploads/tests');
        // Suppression : traversée refusée.
        $canary = BASE_PATH . '/public/canary-test.txt';
        file_put_contents($canary, 'x');
        \App\Services\ImageService::delete('/uploads/../canary-test.txt');
        \App\Services\ImageService::delete('/assets/../canary-test.txt');
        T::ok(is_file($canary), 'Suppression hors /uploads refusée');
        unlink($canary);
        $ht = (string) file_get_contents(BASE_PATH . '/public/uploads/.htaccess');
        T::contains('php_flag engine off', $ht, 'Exécution PHP désactivée dans uploads');
        T::contains('Require all denied', $ht, 'Scripts interdits dans uploads');
        foreach ([$svg, $gif, $html, $jpg] as $f) {
            @unlink($f);
        }
    },

    '25 Sécurité : force brute et abus (limitation de fréquence)' => function (): void {
        T::user('admin', 'bf-admin@example.test');
        $att = new Client('172.16.0.1');
        for ($i = 0; $i < 5; $i++) {
            $att->post('/admin/connexion', ['email' => 'bf-admin@example.test', 'password' => "faux$i"]);
        }
        T::status(422, $att->post('/admin/connexion', ['email' => 'bf-admin@example.test', 'password' => 'Motdepasse123']), 'Admin verrouillé après 5 échecs');
        T::eq(6, (int) DB::value("SELECT COUNT(*) FROM audit_log WHERE action = 'admin.login_failed'"), 'Échecs journalisés');
        $spray = new Client('172.16.0.2');
        for ($i = 0; $i < 20; $i++) {
            $spray->post('/compte/connexion', ['email' => "victime$i@example.test", 'password' => 'Motdepasse123']);
        }
        T::status(422, $spray->post('/admin/connexion', ['email' => 'bf-admin@example.test', 'password' => 'Motdepasse123']), 'Pulvérisation : IP bloquée après 20 échecs');
        $tr = new Client('172.16.0.3');
        for ($i = 0; $i < 10; $i++) {
            $tr->post('/suivi-commande', ['number' => "MPCI-1-$i", 'email' => 'a@example.test']);
        }
        T::status(429, $tr->post('/suivi-commande', ['number' => 'MPCI-1-X', 'email' => 'a@example.test']), 'Suivi : énumération limitée');
        $ct = new Client('172.16.0.4');
        $msg = ['name' => 'A', 'email' => 'a@example.test', 'subject' => 'S', 'message' => 'Message de test assez long', 'consent' => '1'];
        for ($i = 0; $i < 5; $i++) {
            $ct->post('/contact', T::spamOk() + $msg);
        }
        T::status(422, $ct->post('/contact', T::spamOk() + $msg), 'Contact : 6e message refusé');
        T::eq(5, (int) DB::value('SELECT COUNT(*) FROM contact_messages'), 'Seulement 5 messages');
        T::user('customer', 'reset-flood@example.test');
        Mailer::$sent = [];
        $rf = new Client('172.16.0.5');
        for ($i = 0; $i < 6; $i++) {
            $rf->post('/compte/mot-de-passe-oublie', ['email' => 'reset-flood@example.test']);
        }
        T::eq(3, count(Mailer::$sent), 'Réinitialisation : 3 e-mails maximum par heure');
        $wh = new Client('172.16.0.6');
        $last = null;
        for ($i = 0; $i < 121; $i++) {
            $last = $wh->request('POST', '/webhooks/paiement/demo', [], [], '{}');
        }
        T::status(429, $last, 'Webhook : limitation à 120 par minute');
        $rv = new Client('172.16.0.7');
        for ($i = 0; $i < 6; $i++) {
            $last = $rv->post('/boutique/miel-cremeux/avis', T::spamOk() + ['author_name' => 'A', 'rating' => '4', 'comment' => 'Avis numéro ' . $i . ' assez long']);
        }
        T::status(422, $last, 'Avis : 6e envoi refusé');
        T::eq(5, (int) DB::value('SELECT COUNT(*) FROM reviews'), 'Seulement 5 avis');
    },

    '26 Sécurité : logique métier (panier, paiement, commande)' => function (): void {
        $c = new Client('10.10.0.1');
        $json = ['Accept' => 'application/json'];
        $vid = (string) T::variant('MPCI-LIQ-250')['id'];
        foreach (['-5', 'abc', '999999', '1.5', '1e3', ''] as $qty) {
            T::status(422, $c->post('/panier/ajouter', ['variant_id' => $vid, 'qty' => $qty], true, $json), "Quantité « $qty » refusée");
        }
        DB::run("UPDATE product_variants SET deleted_at = ?, is_active = 0 WHERE sku = 'MPCI-LIQ-1000'", [now()]);
        T::status(422, $c->post('/panier/ajouter', ['variant_id' => (string) T::variant('MPCI-LIQ-1000')['id'], 'qty' => '1'], true, $json), 'Variante supprimée refusée');
        T::status(422, $c->post('/panier/ajouter', ['variant_id' => '999999', 'qty' => '1'], true, $json), 'Variante inexistante refusée');
        // Clé d'idempotence forgée.
        $c->addToCart('MPCI-LIQ-250', 1);
        $r = $c->checkout('Abidjan', 'cod', ['idempotency_key' => str_repeat('f', 32)]);
        T::status(422, $r, 'Clé d\'idempotence non émise par le serveur refusée');
        T::eq(0, (int) DB::value('SELECT COUNT(*) FROM orders'), 'Aucune commande');
        T::status(409, $c->checkout('Abidjan', 'cod', ['expected_total' => 'gratuit']), 'Total non numérique → recalcul');
        // Prix modifié entre l'affichage et la validation.
        $c->get('/commande');
        $z = T::zone('Abidjan');
        $est = json_decode($c->get('/commande/estimation?zone_id=' . $z['id'])->body, true);
        DB::run("UPDATE product_variants SET price = 9999 WHERE sku = 'MPCI-LIQ-250'");
        $r = $c->post('/commande', ['idempotency_key' => $c->session['checkout_key'], 'expected_total' => (string) $est['total'], 'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test', 'phone' => '0700000000',
            'zone_id' => (string) $z['id'], 'city' => 'Abidjan', 'address' => 'x', 'payment_method' => 'cod', 'cgv' => '1']);
        T::status(409, $r, 'Changement de prix détecté, confirmation redemandée');
        DB::run("UPDATE product_variants SET price = 2500 WHERE sku = 'MPCI-LIQ-250'");
        // Zone désactivée.
        DB::run("UPDATE shipping_zones SET is_active = 0 WHERE name = 'Bouaké'");
        T::status(422, $c->checkout('Bouaké', 'cod'), 'Zone désactivée refusée');
        // Code promo plafonné : la dernière utilisation ne peut servir qu'une fois.
        DB::run("UPDATE promo_codes SET max_uses = 1, used_count = 0 WHERE code = 'BIENVENUE10'");
        $p1 = new Client('10.10.0.2');
        $p2 = new Client('10.10.0.3');
        foreach ([$p1, $p2] as $p) {
            $p->addToCart('MPCI-LIQ-500', 2);
            $p->post('/panier/code-promo', ['code' => 'BIENVENUE10']);
        }
        Client::orderRef($p1->checkout('Abidjan', 'cod'));
        T::ok(in_array($p2->checkout('Abidjan', 'cod')->status, [303, 422], true) && (int) DB::value("SELECT COUNT(*) FROM orders WHERE promo_code = 'BIENVENUE10'") === 1, 'Code promo épuisé non réutilisé');
        T::eq(1, (int) DB::value("SELECT used_count FROM promo_codes WHERE code = 'BIENVENUE10'"), 'Compteur jamais dépassé');
        // Paiement en ligne sur une commande à la livraison : refusé.
        $cod = new Client('10.10.0.4');
        $cod->addToCart('MPCI-LIQ-250', 1);
        [$n, $t] = Client::orderRef($cod->checkout('Abidjan', 'cod'));
        $cod->post("/commande/$n/payer", ['t' => $t]);
        T::eq(0, (int) DB::value('SELECT COUNT(*) FROM payments'), 'Aucun paiement créé pour une commande COD');
        T::status(404, (new Client('10.10.0.5'))->post("/commande/$n/payer", ['t' => str_repeat('0', 32)]), 'Paiement de la commande d\'autrui impossible');
        // Paiement simulé : choix invalide, désactivation, contenu trop gros.
        $d = new Client('10.10.0.6');
        $d->addToCart('MPCI-LIQ-250', 1);
        [$n2, $t2] = Client::orderRef($d->checkout('Abidjan', 'demo'));
        $ref = explode('?', basename($d->post("/commande/$n2/payer", ['t' => $t2])->headers['Location']))[0];
        T::status(422, $d->post("/paiement/demo/$ref", ['t' => $t2, 'outcome' => 'paid']), 'Issue de paiement invalide');
        Env::set('DEMO_PAYMENT_ENABLED', '0');
        T::status(404, $d->get("/paiement/demo/$ref?t=$t2"), 'Paiement simulé désactivé → 404');
        Env::set('DEMO_PAYMENT_ENABLED', '1');
        T::status(413, (new Client('10.10.0.7'))->request('POST', '/webhooks/paiement/demo', [], [], str_repeat('a', 70000)), 'Notification trop volumineuse refusée');
        Env::set('DEMO_PAYMENT_SECRET', 'court');
        T::eq([], array_values(array_filter(array_column(\App\Services\PaymentService::availableMethods(null), 'code'), static fn ($m) => $m === 'demo')), 'Secret trop court : paiement simulé indisponible');
        Env::set('DEMO_PAYMENT_SECRET', str_repeat('5e', 32));
    },

    '27 Sécurité : divulgation d\'informations et gestion des erreurs' => function (): void {
        Env::set('APP_DEBUG', '0');
        $c = new Client();
        $r = $c->get('/boutique/inconnu');
        T::status(404, $r, '404');
        T::notContains('.php', $r->body, 'Pas de chemin de fichier dans la 404');
        // Erreur interne simulée : base cassée.
        $saved = DB::pdo();
        DB::setPdo(DB::connect(['driver' => 'sqlite', 'database' => ':memory:']));
        $r = $c->get('/boutique');
        DB::setPdo($saved);
        T::status(500, $r, 'Erreur interne');
        foreach (['SQLSTATE', 'no such table', 'Stack', '.php', 'C:\\', '/app/'] as $leak) {
            T::notContains($leak, $r->body, "Aucune fuite technique ($leak)");
        }
        $log = (string) @file_get_contents(BASE_PATH . '/storage/logs/app-' . gmdate('Y-m-d') . '.log');
        T::contains('no such table', $log, 'Erreur détaillée journalisée côté serveur');
        T::fresh();
        Env::set('APP_DEBUG', '0');
        // Messages de connexion identiques (pas d'énumération par la connexion).
        T::user('customer', 'exists@example.test');
        $m1 = (new Client('10.11.0.1'))->post('/compte/connexion', ['email' => 'exists@example.test', 'password' => 'mauvais'])->body;
        $m2 = (new Client('10.11.0.2'))->post('/compte/connexion', ['email' => 'absent@example.test', 'password' => 'mauvais'])->body;
        preg_match('#<li>(.*?)</li>#s', $m1, $a);
        preg_match('#<li>(.*?)</li>#s', $m2, $b);
        T::eq($a[1] ?? 'a', $b[1] ?? 'b', 'Même message pour compte existant ou non');
        $r = $c->get('/commande/estimation?zone_id=1');
        T::eq('application/json; charset=UTF-8', $r->headers['Content-Type'], 'JSON typé');
        T::status(404, $c->get('/storage/logs/app.log'), 'Journaux non servis');
        T::status(404, $c->get('/.env'), '.env non servi');
        T::notContains('APP_KEY', (string) file_get_contents(BASE_PATH . '/.env.example') === '' ? 'APP_KEY' : 'ok', 'Exemple de configuration présent');
        T::ok(!preg_match('/^APP_KEY=.+$/m', (string) file_get_contents(BASE_PATH . '/.env.example')), 'Aucun secret dans .env.example');
        $gitignore = (string) file_get_contents(BASE_PATH . '/.gitignore');
        foreach (['.env', '/storage/*.local.txt', '/public/uploads/*'] as $rule) {
            T::contains($rule, $gitignore, "Ignoré par git : $rule");
        }
        Env::set('APP_DEBUG', '1');
    },

    // ===================== Itération 2 : paiements intégrés, bilinguisme, thème =====================

    '28 Paiements intégrés (Wave, MTN MoMo, Orange Money, carte) contre les simulateurs' => function (): void {
        T::providers();
        T::eq(['cod', 'wave', 'orange_money', 'mtn_momo', 'card', 'demo'], array_column(PaymentService::availableMethods(T::zone('Abidjan')), 'code'), 'Moyens proposés une fois configurés');
        \App\Services\Settings::set('wave_enabled', '0');
        T::ok(!in_array('wave', array_column(PaymentService::availableMethods(null), 'code'), true), 'Moyen désactivable en administration');
        \App\Services\Settings::set('wave_enabled', '1');
        Env::set('CINETPAY_API_KEY', '');
        T::ok(!in_array('card', array_column(PaymentService::availableMethods(null), 'code'), true), 'Moyen non proposé sans ses clés');
        // Page Intégrations : état réel de chaque prestataire, URL de notification, aucun secret affiché.
        $adm = new Client('10.20.0.9');
        $adm->loginAs(T::user('admin', 'integrations@example.test'));
        $page = html_entity_decode($adm->get('/admin/integrations')->body);
        T::eq(3, substr_count($page, 'Actif — proposé aux clients'), 'Intégrations : Wave, Orange, MTN (configurés) affichés actifs');
        T::contains('Clés manquantes', $page, 'Intégrations : prestataire sans clé signalé (carte)');
        T::contains('/webhooks/paiement/wave', $page, 'Intégrations : URL de notification à déclarer');
        T::notContains('cp_api_key', $page, 'Intégrations : aucune valeur secrète affichée');
        T::notContains('Non intégré', $page, 'Intégrations : plus de mention « non intégré »');
        Env::set('CINETPAY_API_KEY', 'cp_api_key');
        $wh = new Client('41.0.0.2');

        // Wave : redirection, notification signée, rejeu, retour client vérifié
        $c = new Client('10.20.0.1');
        $c->addToCart('MPCI-LIQ-500', 1);
        $r = $c->checkout('Abidjan', 'wave');
        T::ok(str_starts_with($r->headers['Location'] ?? '', 'https://pay.wave.com/c/cos-'), 'Redirection vers la page Wave');
        $pay = DB::one("SELECT * FROM payments WHERE provider = 'wave'");
        T::ok(str_starts_with((string) $pay['provider_token'], 'cos-'), 'Session Wave enregistrée');
        T::eq(7000, (int) $pay['amount'], 'Montant calculé par le serveur envoyé à Wave');
        $event = json_encode(['id' => 'EV_1', 'type' => 'checkout.session.completed', 'data' => ['id' => $pay['provider_token'], 'amount' => (string) $pay['amount'], 'currency' => 'XOF',
            'payment_status' => 'succeeded', 'checkout_status' => 'complete', 'client_reference' => $pay['reference'], 'transaction_id' => 'TCN1']]);
        $ts = time();
        $sig = hash_hmac('sha256', $ts . $event, 'wave_webhook_secret_for_tests');
        T::status(401, $wh->request('POST', '/webhooks/paiement/wave', [], ['Wave-Signature' => "t=$ts,v1=" . str_repeat('0', 64)], $event), 'Signature Wave invalide → 401');
        $old = $ts - 900;
        T::status(400, $wh->request('POST', '/webhooks/paiement/wave', [], ['Wave-Signature' => "t=$old,v1=" . hash_hmac('sha256', $old . $event, 'wave_webhook_secret_for_tests')], $event), 'Horodatage Wave expiré → 400');
        T::eq('paid', json_decode($wh->request('POST', '/webhooks/paiement/wave', [], ['Wave-Signature' => "t=$ts,v1=$sig"], $event)->body, true)['result'] ?? null, 'Notification Wave signée → commande payée');
        T::eq('duplicate', json_decode($wh->request('POST', '/webhooks/paiement/wave', [], ['Wave-Signature' => "t=$ts,v1=$sig"], $event)->body, true)['result'] ?? null, 'Rejeu Wave ignoré');
        $c->addToCart('MPCI-LIQ-250', 1);
        $c->checkout('Abidjan', 'wave');
        $p2 = DB::one("SELECT * FROM payments WHERE provider = 'wave' ORDER BY id DESC LIMIT 1");
        $o2 = OrderService::find((int) $p2['order_id']);
        $token2 = ((array) $c->session['order_tokens'])[$o2['number']];
        $c->get('/paiement/retour/wave?ref=' . $p2['reference'] . '&t=' . $token2);
        T::eq('pending_payment', OrderService::find((int) $o2['id'])['status'], 'Retour sans paiement chez Wave : aucune validation');
        T::providerSet('wave', $p2['provider_token'], 'succeeded');
        $back = $c->get('/paiement/retour/wave?ref=' . $p2['reference'] . '&t=' . $token2);
        T::redirectsTo('/commande/' . $o2['number'], $back, 'Retour client redirigé vers la commande');
        T::eq('paid', OrderService::find((int) $o2['id'])['status'], 'Retour client : état confirmé auprès de Wave');
        T::status(404, (new Client('10.20.0.8'))->get('/paiement/retour/wave?ref=' . $p2['reference']), 'Retour sans jeton refusé');

        // MTN MoMo : demande poussée sur le téléphone, interrogation, rappel forgé sans effet
        $m = new Client('10.20.0.2');
        $m->addToCart('MPCI-CIRE-250', 1);
        T::status(422, $m->checkout('Abidjan', 'mtn_momo', ['payer_phone' => '123']), 'Numéro Mobile Money invalide refusé');
        $r = $m->checkout('Abidjan', 'mtn_momo', ['payer_phone' => '05 44 33 22 11']);
        T::ok(preg_match('#/commande/(MPCI-[0-9]+-[A-F0-9]+)/paiement\?t=([a-f0-9]{32})#', $r->headers['Location'] ?? '', $mm) === 1, 'Page d\'attente de validation');
        [, $mn, $mt] = $mm;
        $mp = DB::one("SELECT * FROM payments WHERE provider = 'mtn_momo'");
        T::eq('05 44 33 22 11', $mp['payer_phone'], 'Numéro payeur enregistré');
        T::status(200, $m->get("/commande/$mn/paiement?t=$mt"), 'Page d\'attente affichée');
        T::eq(false, json_decode($m->get("/commande/$mn/paiement/statut?t=$mt")->body, true)['done'], 'En attente de validation');
        $wh->request('POST', '/webhooks/paiement/mtn_momo', [], [], json_encode(['externalId' => $mp['reference'], 'status' => 'SUCCESSFUL', 'amount' => $mp['amount']]));
        T::eq('pending_payment', OrderService::findByNumber($mn)['status'], 'Rappel MoMo forgé « SUCCESSFUL » sans effet (état réel : PENDING)');
        T::providerSet('mtn', $mp['provider_token'], 'SUCCESSFUL');
        DB::run('UPDATE payments SET last_checked_at = NULL WHERE id = ?', [(int) $mp['id']]);
        $st = json_decode($m->get("/commande/$mn/paiement/statut?t=$mt")->body, true);
        T::ok($st['done'] && $st['order_status'] === 'paid', 'Validation sur le téléphone → commande payée');
        T::status(404, (new Client('10.20.0.9'))->get("/commande/$mn/paiement/statut"), 'Statut protégé par le jeton');
        $m->addToCart('MPCI-CIRE-250', 1);
        $m->checkout('Abidjan', 'mtn_momo', ['payer_phone' => '0544332211']);
        $mp2 = DB::one("SELECT * FROM payments WHERE provider = 'mtn_momo' ORDER BY id DESC LIMIT 1");
        T::providerSet('mtn', $mp2['provider_token'], 'FAILED');
        T::eq('failed', json_decode($wh->request('POST', '/webhooks/paiement/mtn_momo', [], [], json_encode(['externalId' => $mp2['reference'], 'referenceId' => $mp2['provider_token']]))->body, true)['result'], 'Rappel MoMo → vérification → échec enregistré');

        // Orange Money : jeton de notification + confirmation par transactionstatus
        $om = new Client('10.20.0.3');
        $om->addToCart('MPCI-LIQ-500', 2);
        $r = $om->checkout('Abidjan', 'orange_money');
        T::ok(str_starts_with($r->headers['Location'] ?? '', 'https://webpayment-qualif.orange-money.com/'), 'Redirection vers Orange Money');
        $op = DB::one("SELECT * FROM payments WHERE provider = 'orange_money'");
        [, $notifToken] = explode('|', (string) $op['provider_token']);
        T::eq('ignored', json_decode($wh->request('POST', '/webhooks/paiement/orange_money', [], [], json_encode(['status' => 'SUCCESS', 'notif_token' => str_repeat('a', 32), 'txnid' => 'X']))->body, true)['result'], 'Jeton de notification inconnu ignoré');
        T::eq('pending', json_decode($wh->request('POST', '/webhooks/paiement/orange_money', [], [], json_encode(['status' => 'SUCCESS', 'notif_token' => $notifToken, 'txnid' => 'X']))->body, true)['result'], 'Notification non confirmée par Orange : en attente');
        T::eq('pending_payment', OrderService::find((int) $op['order_id'])['status'], 'Aucune validation sans confirmation d\'Orange');
        T::providerSet('orange', $op['reference'], 'SUCCESS');
        T::eq('paid', json_decode($wh->request('POST', '/webhooks/paiement/orange_money', [], [], json_encode(['status' => 'SUCCESS', 'notif_token' => $notifToken, 'txnid' => 'X']))->body, true)['result'], 'Confirmation Orange → commande payée');

        // Carte via CinetPay : page hébergée, confirmation par /v2/payment/check, contrôle du montant
        $cp = new Client('10.20.0.4');
        $cp->addToCart('MPCI-COF-3', 1);
        $r = $cp->checkout('Abidjan', 'card');
        T::ok(str_starts_with($r->headers['Location'] ?? '', 'https://checkout.cinetpay.com/'), 'Redirection vers la page de paiement par carte');
        $cpay = DB::one("SELECT * FROM payments WHERE provider = 'card'");
        $form = ['cpm_site_id' => '123456', 'cpm_trans_id' => $cpay['reference']];
        T::eq('pending', json_decode($wh->request('POST', '/webhooks/paiement/card', $form, ['x-token' => 'faux'])->body, true)['result'], 'Notification carte non confirmée : en attente');
        T::providerSet('cinetpay', $cpay['reference'], 'ACCEPTED', 1);
        T::eq('amount_mismatch', json_decode($wh->request('POST', '/webhooks/paiement/card', $form)->body, true)['result'], 'Montant différent détecté et refusé');
        T::providerSet('cinetpay', $cpay['reference'], 'ACCEPTED', (int) $cpay['amount']);
        T::eq('paid', json_decode($wh->request('POST', '/webhooks/paiement/card', $form)->body, true)['result'], 'Carte acceptée → commande payée');
        T::status(422, $wh->request('POST', '/webhooks/paiement/card', ['cpm_site_id' => '999', 'cpm_trans_id' => $cpay['reference']]), 'Notification d\'un autre site refusée');

        // Vérification auprès du prestataire avant toute annulation pour expiration
        $e = new Client('10.20.0.5');
        $e->addToCart('MPCI-LIQ-250', 1);
        $e->checkout('Abidjan', 'wave');
        $ep = DB::one("SELECT * FROM payments WHERE provider = 'wave' ORDER BY id DESC LIMIT 1");
        T::providerSet('wave', $ep['provider_token'], 'succeeded');
        DB::run('UPDATE orders SET expires_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 60), (int) $ep['order_id']]);
        T::eq(0, OrderService::expireOverdue(), 'Expiration : le prestataire est interrogé d\'abord (notification perdue)');
        T::eq('paid', OrderService::find((int) $ep['order_id'])['status'], 'Paiement reçu juste avant l\'échéance : commande payée, pas annulée');
        // Prestataire injoignable à l'échéance : pas d'annulation à l'aveugle pendant 24 h, puis annulation signalée.
        $u = new Client('10.20.0.7');
        $u->addToCart('MPCI-LIQ-250', 1);
        $u->checkout('Abidjan', 'wave');
        $up = DB::one("SELECT * FROM payments WHERE provider = 'wave' ORDER BY id DESC LIMIT 1");
        DB::run('UPDATE orders SET expires_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 60), (int) $up['order_id']]);
        Env::set('WAVE_API_BASE', 'http://127.0.0.1:1');
        T::eq(0, OrderService::expireOverdue(), 'Prestataire injoignable : commande conservée (nouvel essai plus tard)');
        T::eq('pending_payment', OrderService::find((int) $up['order_id'])['status'], 'Toujours en attente de paiement');
        DB::run('UPDATE orders SET expires_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 86400 - 60), (int) $up['order_id']]);
        T::eq(1, OrderService::expireOverdue(), 'Après 24 h sans réponse : annulation (stock libéré)');
        T::contains('invérifiable', (string) DB::value("SELECT note FROM order_status_history WHERE order_id = ? ORDER BY id DESC LIMIT 1", [(int) $up['order_id']]), 'Annulation signalée « à contrôler »');
        Env::set('WAVE_API_BASE', T::MOCK . '/wave');

        // Prestataire injoignable : message clair, commande conservée
        Env::set('WAVE_API_BASE', 'http://127.0.0.1:1');
        $d = new Client('10.20.0.6');
        $d->addToCart('MPCI-LIQ-250', 1);
        $r = $d->checkout('Abidjan', 'wave');
        T::redirectsTo('/commande/', $r, 'Prestataire injoignable : retour à la page de commande');
        T::contains('momentanément indisponible', implode(' ', (array) ($d->session['_flash']['error'] ?? [])), 'Message d\'indisponibilité');
        T::eq('failed', DB::value("SELECT status FROM payments WHERE provider = 'wave' ORDER BY id DESC LIMIT 1"), 'Tentative marquée en échec');
        T::eq(0, (int) DB::value("SELECT COUNT(*) FROM payments WHERE checkout_url LIKE '%card%' OR provider_reference LIKE '4%'"), 'Aucune donnée de carte conservée');
    },

    '29 Bilinguisme FR/EN et thème clair/sombre' => function (): void {
        $c = new Client('10.30.0.1');
        $en = $c->get('/en/boutique/miel-liquide-toutes-fleurs');
        T::status(200, $en, 'Fiche produit en anglais');
        T::contains('<html lang="en"', $en->body, 'Langue déclarée');
        T::eq('en', $en->headers['Content-Language'] ?? '', 'En-tête Content-Language');
        T::contains('Multifloral liquid honey', $en->body, 'Nom du produit traduit');
        T::contains('2,500 FCFA', T::plain($en->body), 'Montant au format anglais');
        T::contains('Add to cart', $en->body, 'Interface traduite');
        T::notContains('Ajouter au panier', $en->body, 'Aucun libellé français résiduel');
        T::contains('href="/en/panier"', $en->body, 'Liens internes préfixés par /en');
        T::contains('hreflang="fr"', $en->body, 'Alternative hreflang');
        T::contains('<html lang="fr"', $c->get('/boutique/miel-liquide-toutes-fleurs')->body, 'Version française inchangée');
        $r = $c->post('/en/panier/ajouter', ['variant_id' => (string) T::variant('MPCI-LIQ-500')['id'], 'qty' => '1']);
        T::redirectsTo('/en/panier', $r, 'Redirections conservées en anglais');
        $c->get('/en/commande');
        $z = T::zone('Abidjan');
        $est = json_decode($c->get('/en/commande/estimation?zone_id=' . $z['id'])->body, true);
        T::contains('7,000', T::plain($est['formatted']['total']), 'Estimation au format anglais');
        Mailer::$sent = [];
        $r = $c->post('/en/commande', ['idempotency_key' => $c->session['checkout_key'], 'expected_total' => (string) $est['total'], 'first_name' => 'John', 'last_name' => 'Doe',
            'email' => 'john@example.test', 'phone' => '0700000000', 'zone_id' => (string) $z['id'], 'city' => 'Abidjan', 'address' => 'Plateau', 'payment_method' => 'cod', 'cgv' => '1']);
        T::ok(str_starts_with($r->headers['Location'] ?? '', '/en/commande/MPCI-'), 'Page de commande en anglais');
        $o = DB::one('SELECT * FROM orders ORDER BY id DESC LIMIT 1');
        T::eq('en', $o['locale'], 'Langue de la commande mémorisée');
        T::contains('received', Mailer::$sent[0]['subject'] ?? '', 'E-mail de confirmation en anglais');
        T::contains('/en/commande/' . $o['number'], Mailer::$sent[0]['body'] ?? '', 'Lien anglais dans l\'e-mail');
        OrderService::transition((int) $o['id'], 'preparing', null);
        T::contains('being prepared', end(Mailer::$sent)['body'], 'Notification de statut dans la langue du client');
        $bad = $c->post('/en/contact', T::spamOk() + ['name' => '', 'email' => 'x', 'subject' => '', 'message' => '']);
        T::contains('Invalid email address.', $bad->body, 'Messages de validation traduits');
        $a = new Client('10.30.0.2');
        $a->loginAs(T::user('admin', 'i18n-admin@example.test'));
        T::contains('Tableau de bord', $a->get('/en/admin')->body, 'Administration toujours en français');
        foreach (DB::all('SELECT id FROM products') as $p) {
            T::ok(count(\App\Services\I18n::get('product', (int) $p['id'])) >= 5, 'Produit ' . $p['id'] . ' traduit');
        }
        foreach (DB::all('SELECT id FROM pages') as $p) {
            T::ok(isset(\App\Services\I18n::get('page', (int) $p['id'])['body']), 'Page ' . $p['id'] . ' traduite');
        }
        $sm = $c->get('/sitemap.xml')->body;
        T::contains('/en/boutique/miel-liquide-toutes-fleurs', $sm, 'Plan du site bilingue');
        T::contains('hreflang="en"', $sm, 'Alternatives dans le plan du site');
        // Thème
        $dark = new Client('10.30.0.3');
        $dark->cookies['mpci_theme'] = 'dark';
        T::contains('data-theme="dark"', $dark->get('/')->body, 'Thème sombre mémorisé (sans flash)');
        $evil = new Client('10.30.0.4');
        $evil->cookies['mpci_theme'] = '"><script>';
        T::notContains('<script>"', $evil->get('/')->body, 'Valeur de thème non valide ignorée');
        $t = new Client('10.30.0.5');
        $r = $t->post('/preferences/theme', ['theme' => 'light', 'retour' => '/menu']);
        T::eq('light', $t->cookies['mpci_theme'] ?? null, 'Choix du thème sans JavaScript');
        T::redirectsTo('/menu', $r, 'Retour à la page d\'origine');
        $t->post('/preferences/theme', ['theme' => 'auto']);
        T::ok(!isset($t->cookies['mpci_theme']), 'Retour au thème automatique');
        T::redirectsTo('/', $t->post('/preferences/theme', ['theme' => 'dark', 'retour' => 'https://evil.example']), 'Pas de redirection ouverte via le thème');
    },

    '30 Traductions anglaises saisies dans l\'administration' => function (): void {
        $a = new Client('10.31.0.1');
        $a->loginAs(T::user('admin', 'trad@example.test'));
        $p = DB::one("SELECT * FROM products WHERE slug = 'miel-liquide-toutes-fleurs'");
        $form = $a->get('/admin/produits/' . $p['id'])->body;
        T::contains('Traduction anglaise', $form, 'Encadré de traduction sur la fiche produit');
        T::contains('name="en[description]"', $form, 'Champ description anglaise');
        // Produit : nom anglais + tentative d'injection (champ non prévu, script).
        $a->post('/admin/produits/' . $p['id'], ['name' => $p['name'], 'slug' => $p['slug'], 'category_id' => (string) $p['category_id'], 'is_active' => '1',
            'description' => (string) $p['description'], 'en' => ['name' => 'Wildflower honey (edited)', 'short_description' => '<script>alert(1)</script>', 'price' => '1']]);
        $en = (new Client('10.31.0.2'))->get('/en/boutique/miel-liquide-toutes-fleurs')->body;
        T::contains('Wildflower honey (edited)', $en, 'Nom anglais affiché sur /en');
        T::notContains('<script>alert(1)</script>', $en, 'Texte anglais échappé');
        T::contains($p['name'], (new Client('10.31.0.3'))->get('/boutique/miel-liquide-toutes-fleurs')->body, 'Version française inchangée');
        T::eq(0, (int) DB::value("SELECT COUNT(*) FROM translations WHERE field = 'price'"), 'Champ non prévu ignoré');
        T::ok(DB::value("SELECT id FROM audit_log WHERE action = 'translation.save' AND entity_type = 'product'") !== null, 'Traduction journalisée');
        // Champ vidé = retour au texte français.
        $a->post('/admin/produits/' . $p['id'], ['name' => $p['name'], 'slug' => $p['slug'], 'category_id' => (string) $p['category_id'], 'is_active' => '1', 'en' => ['name' => '']]);
        T::notContains('Wildflower honey (edited)', (new Client('10.31.0.4'))->get('/en/boutique/miel-liquide-toutes-fleurs')->body, 'Traduction vidée : plus affichée');
        // Sans champ en[...] (autre formulaire), la traduction existante n'est pas effacée.
        T::ok((\App\Services\I18n::get('product', (int) $p['id'])['description'] ?? '') !== '', 'Traduction conservée quand le formulaire ne l\'envoie pas');
        // Variante
        $v = T::variant('MPCI-LIQ-500');
        $a->post('/admin/variantes/' . $v['id'], ['label' => $v['label'], 'format' => $v['format'], 'sku' => $v['sku'], 'price' => (string) $v['price'], 'sort_order' => '0', 'is_active' => '1', 'en' => ['label' => '500 g jar']]);
        T::contains('500 g jar', (new Client('10.31.0.5'))->get('/en/boutique/miel-liquide-toutes-fleurs')->body, 'Format traduit');
        // FAQ
        $a->post('/admin/faq', ['question' => 'Question test ?', 'answer' => 'Réponse test.', 'category' => 'Général', 'sort_order' => '0', 'is_active' => '1',
            'en' => ['question' => 'Test question in English?', 'answer' => 'English answer.']]);
        T::contains('Test question in English?', (new Client('10.31.0.6'))->get('/en/faq')->body, 'FAQ traduite');
        $faqId = (int) DB::value("SELECT id FROM faqs WHERE question = 'Question test ?'");
        $a->post('/admin/faq/' . $faqId . '/supprimer');
        T::eq(0, (int) DB::value("SELECT COUNT(*) FROM translations WHERE entity_type = 'faq' AND entity_id = ?", [$faqId]), 'Traductions supprimées avec la question');
        // Zone
        $z = T::zone('Bouaké');
        $a->post('/admin/livraison', ['id' => (string) $z['id'], 'name' => $z['name'], 'fee' => (string) $z['fee'], 'sort_order' => '0', 'allows_cod' => '1', 'is_active' => '1',
            'en' => ['name' => 'Bouake (city)', 'delay_text' => '2 to 3 days']]);
        $buyer = new Client('10.31.0.7');
        $buyer->addToCart('MPCI-LIQ-500', 1);
        T::contains('Bouake (city)', $buyer->get('/en/commande')->body, 'Zone traduite au paiement');
        // Page
        $page = DB::one("SELECT * FROM pages WHERE slug = 'a-propos'");
        $a->post('/admin/pages/' . $page['id'], ['title' => $page['title'], 'body' => $page['body'], 'en' => ['title' => 'About us (edited)', 'body' => $page['body']]]);
        T::contains('About us (edited)', (new Client('10.31.0.8'))->get('/en/a-propos')->body, 'Page traduite');
        // Accueil
        $keys = \App\Controllers\Admin\ContentController::HOME_KEYS;
        $a->post('/admin/contenu', array_merge(array_combine($keys, array_map(static fn ($k) => \App\Services\Settings::get($k), $keys)),
            ['home_hero_title_en' => 'Honey straight from Katiola (edited)']));
        T::contains('Honey straight from Katiola (edited)', (new Client('10.31.0.9'))->get('/en')->body, 'Accueil anglais modifiable');
    },

    '31 Séparation espace client / administration, logo, favicon et photos' => function (): void {
        $adminLink = static fn (string $html): bool => (bool) preg_match('#href="(?:https?://[^"/]+)?(?:/en)?/admin[/"?]#', $html) || str_contains($html, 'btn-admin');
        $publicPages = ['/', '/boutique', '/boutique/miel-liquide-toutes-fleurs', '/a-propos', '/menu', '/faq', '/contact', '/panier', '/en', '/en/menu'];

        // Visiteur anonyme
        $anon = new Client('10.32.0.1');
        foreach ($publicPages as $p) {
            T::ok(!$adminLink($anon->get($p)->body), "Anonyme : aucun lien vers l'administration sur $p");
        }
        T::redirectsTo('/admin/connexion?retour=%2Fadmin%2Fcommandes', $anon->get('/admin/commandes'), 'Anonyme → connexion de l\'équipe, avec retour');
        T::redirectsTo('/compte/connexion', $anon->get('/compte/commandes'), 'Anonyme : espace client protégé');
        T::status(403, $anon->get('/admin/commandes', ['Accept' => 'application/json']), 'Anonyme : appel JSON à l\'administration refusé');
        T::status(403, $anon->post('/admin/produits', ['name' => 'X']), 'Anonyme : opération d\'administration refusée');

        // Client : redirections selon le rôle, jamais vers l'administration
        T::user('customer', 'sep-client@example.test');
        $c = new Client('10.32.0.2');
        T::redirectsTo('/compte', $c->post('/compte/connexion', ['email' => 'sep-client@example.test', 'password' => 'Motdepasse123', 'retour' => '/admin/equipe']), 'Client : retour vers /admin ignoré, envoyé vers son compte');
        $c2 = new Client('10.32.0.3');
        T::redirectsTo('/panier', $c2->post('/compte/connexion', ['email' => 'sep-client@example.test', 'password' => 'Motdepasse123', 'retour' => '/panier']), 'Client : retour vers la page d\'origine');
        foreach (array_merge($publicPages, ['/compte', '/compte/commandes', '/compte/profil']) as $p) {
            $page = $c->get($p);
            T::ok(!$adminLink($page->body), "Client : aucun lien vers l'administration sur $p");
        }
        T::notContains('Administration', $c->get('/')->body, 'Client : aucun bouton « Administration »');
        foreach (['/admin', '/admin/commandes', '/admin/equipe', '/admin/connexion', '/en/admin'] as $p) {
            $r = $c->get($p);
            T::status(403, $r, "Client : $p → accès refusé");
            T::ok(!$adminLink($r->body), "Client : la page de refus ($p) ne mène pas à l'administration");
        }
        $denied = $c->get('/admin');
        T::contains('Accès refusé', $denied->body, 'Message d\'accès refusé');
        T::contains('href="/compte"', $denied->body, 'Lien de retour vers l\'espace client');
        T::status(403, $c->get('/admin/commandes', ['Accept' => 'application/json']), 'Client : API d\'administration refusée');
        T::status(403, $c->post('/admin/produits', ['name' => 'Piratage']), 'Client : création de produit refusée');
        T::status(403, $c->post('/admin/equipe', ['email' => 'x@example.test', 'role' => 'admin']), 'Client : création de compte d\'équipe refusée');
        T::eq(0, (int) DB::value("SELECT COUNT(*) FROM products WHERE name = 'Piratage'"), 'Aucune donnée modifiée');
        T::ok(isset($c->session['user_id']), 'Client toujours connecté après les refus (pas de déconnexion ni de boucle)');
        T::eq('private, no-cache', $c->get('/')->headers['Cache-Control'] ?? '', 'Page personnalisée non mise en cache partagé');
        // Pas de boucle de redirection : chaque parcours aboutit en moins de 3 étapes.
        foreach (['/compte/connexion', '/admin', '/compte'] as $p) {
            T::ok(in_array($c->follow($c->get($p))->status, [200, 403], true), "Client : $p aboutit sans boucle");
        }
        T::redirectsTo('/', $c->post('/compte/deconnexion'), 'Client : déconnexion → accueil');

        // Administrateur : deux espaces, navigation dans les deux sens sans reconnexion
        T::user('admin', 'sep-admin@example.test');
        $a = new Client('10.32.0.4');
        T::redirectsTo('/admin', $a->post('/compte/connexion', ['email' => 'sep-admin@example.test', 'password' => 'Motdepasse123']), 'Équipe : connexion publique → administration');
        $home = $a->get('/');
        T::ok($adminLink($home->body), 'Équipe : bouton « Administration » sur le site public');
        T::contains('Administration', $home->body, 'Libellé « Administration »');
        T::ok($adminLink($a->get('/menu')->body), 'Équipe : entrée Administration dans le menu');
        $dash = $a->get('/admin');
        T::status(200, $dash, 'Équipe : tableau de bord');
        T::contains('Voir le site', $dash->body, 'Bouton « Voir le site » dans l\'administration');
        T::notContains('target="_blank" rel="noopener">Voir le site', $dash->body, '« Voir le site » dans le même onglet');
        T::status(200, $a->get('/boutique'), 'Équipe : retour au site public');
        T::status(200, $a->get('/admin/commandes'), 'Équipe : retour à l\'administration sans reconnexion');
        T::redirectsTo('/admin/commandes', $a->get('/admin/connexion?retour=%2Fadmin%2Fcommandes'), 'Équipe déjà connectée : connexion → page demandée');
        T::redirectsTo('/admin', $a->get('/admin/connexion?retour=https%3A%2F%2Fevil.example'), 'Retour externe ignoré');
        T::redirectsTo('/admin', $a->get('/admin/connexion?retour=%2Fadmin%2Fconnexion'), 'Retour vers la connexion ignoré (pas de boucle)');
        T::redirectsTo('/admin', $a->get('/compte/connexion'), 'Équipe déjà connectée sur la connexion publique → administration');
        $b = new Client('10.32.0.5');
        T::redirectsTo('/boutique', $b->post('/compte/connexion', ['email' => 'sep-admin@example.test', 'password' => 'Motdepasse123', 'retour' => '/boutique']), 'Équipe : retour vers la page publique d\'origine');
        $d = new Client('10.32.0.6');
        T::redirectsTo('/admin/commandes', $d->post('/admin/connexion', ['email' => 'sep-admin@example.test', 'password' => 'Motdepasse123', 'retour' => '/admin/commandes']), 'Connexion de l\'équipe → page d\'administration demandée');
        $e = new Client('10.32.0.7');
        T::redirectsTo('/admin', $e->post('/admin/connexion', ['email' => 'sep-admin@example.test', 'password' => 'Motdepasse123', 'retour' => '/compte']), 'Connexion de l\'équipe : retour hors administration ignoré');
        T::redirectsTo('/admin/connexion', $a->post('/admin/deconnexion'), 'Équipe : déconnexion → page de connexion');
        T::ok(!$adminLink($a->get('/')->body), 'Après déconnexion : plus de lien vers l\'administration');

        // Rôle restreint : lien vers l'administration, mais permissions vérifiées route par route
        $cm = new Client('10.32.0.8');
        $cm->loginAs(T::user('catalog_manager', 'sep-cat@example.test'));
        T::ok($adminLink($cm->get('/')->body), 'Gestionnaire catalogue : lien Administration');
        T::status(200, $cm->get('/admin/produits'), 'Gestionnaire catalogue : catalogue accessible');
        T::status(403, $cm->get('/admin/equipe'), 'Gestionnaire catalogue : équipe refusée');
        T::status(403, $cm->post('/admin/reglages', ['store_name' => 'X']), 'Gestionnaire catalogue : réglages refusés');

        // Logo et favicon : emplacements prêts, aucun logo inventé
        $html = $anon->get('/')->body;
        T::notContains('brand-mark', $html, 'Ancien pictogramme inventé retiré');
        T::ok(brand_logo_url() !== null ? str_contains($html, 'class="brand-logo"') : str_contains($html, 'brand-wordmark'), 'Marque : logo officiel s\'il est installé, sinon nom en toutes lettres');
        foreach (['favicon.ico', 'favicon-32.png', 'apple-touch-icon.png', 'site.webmanifest'] as $f) {
            T::contains('/assets/img/brand/' . $f, $html, "Balise d'icône $f");
            T::ok(is_file(BASE_PATH . '/public/assets/img/brand/' . $f), "Fichier $f présent");
        }
        T::ok(is_file(BASE_PATH . '/public/favicon.ico'), '/favicon.ico à la racine');
        T::contains('/assets/img/brand/favicon.ico', $anon->get('/admin/connexion')->body, 'Favicon sur la connexion de l\'équipe');

        // Photos : toutes les images affichées existent, origine indiquée, crédits complets
        foreach (['/', '/boutique', '/a-propos', '/commandes-professionnelles', '/credits-photos', '/boutique/miel-cremeux', '/boutique/coffret-decouverte', '/en'] as $p) {
            $body = $anon->get($p)->body;
            preg_match_all('#<img[^>]+src="(/[^"?]+)#', $body, $m);
            T::ok($m[1] !== [], "Images présentes sur $p");
            foreach ($m[1] as $src) {
                T::ok(is_file(BASE_PATH . '/public' . $src), "Image $src (page $p) trouvée");
            }
            T::notContains('/assets/img/products/', $body, "Plus d'illustration générique SVG sur $p");
        }
        T::contains('fb-miel-en-rayon-vente', $anon->get('/')->body, 'Accueil : vraie photo de l\'entreprise');
        T::contains('Photo d\'illustration', html_entity_decode($anon->get('/boutique/miel-cremeux')->body), 'Photo générique signalée comme illustration');
        $credits = html_entity_decode($anon->get('/credits-photos')->body);
        foreach (photos() as $key => $p) {
            T::ok(is_file(BASE_PATH . '/public/assets/img/photos/' . $p['file']), "Photo $key présente");
            if ($p['origin'] === 'illustration') {
                T::contains($p['author'], $credits, "Crédit de $key (auteur)");
                T::contains($p['license'], $credits, "Crédit de $key (licence)");
            }
        }
    },
];
