<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\AccountController;
use App\Controllers\Admin;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\HomeController;
use App\Controllers\PageController;
use App\Controllers\PaymentController;
use App\Controllers\SeoController;
use App\Controllers\ShopController;

$order = '{number:MPCI-[0-9]+-[A-F0-9]+}';

// Site public
$router->get('/', [HomeController::class, 'index']);
$router->get('/boutique', [ShopController::class, 'index']);
$router->get('/boutique/{slug}', [ShopController::class, 'show']);
$router->post('/boutique/{slug}/avis', [ShopController::class, 'review']);

$router->get('/panier', [CartController::class, 'show']);
$router->post('/panier/ajouter', [CartController::class, 'add']);
$router->post('/panier/modifier', [CartController::class, 'update']);
$router->post('/panier/retirer', [CartController::class, 'remove']);
$router->post('/panier/code-promo', [CartController::class, 'promo']);
$router->post('/panier/code-promo/retirer', [CartController::class, 'removePromo']);

$router->get('/commande', [CheckoutController::class, 'form']);
$router->get('/commande/estimation', [CheckoutController::class, 'estimate']);
$router->post('/commande', [CheckoutController::class, 'store']);
$router->get('/commande/' . $order, [CheckoutController::class, 'show']);
$router->post('/commande/' . $order . '/payer', [CheckoutController::class, 'pay']);
$router->get('/commande/' . $order . '/recu', [CheckoutController::class, 'receipt']);
$router->get('/commande/' . $order . '/paiement', [CheckoutController::class, 'waiting']);
$router->get('/commande/' . $order . '/paiement/statut', [CheckoutController::class, 'paymentStatus']);

$router->get('/paiement/demo/{reference:PAY-[A-F0-9]+}', [PaymentController::class, 'demo']);
$router->post('/paiement/demo/{reference:PAY-[A-F0-9]+}', [PaymentController::class, 'demoSubmit']);
$router->get('/paiement/retour/{provider:[a-z_]+}', [PaymentController::class, 'returnFrom']);
$router->post('/webhooks/paiement/{provider:[a-z_]+}', [PaymentController::class, 'webhook'], ['nocsrf']);

$router->get('/suivi-commande', [PageController::class, 'tracking']);
$router->post('/suivi-commande', [PageController::class, 'trackingLookup']);
$router->get('/a-propos', [PageController::class, 'about']);
$router->get('/contact', [PageController::class, 'contact']);
$router->post('/contact', [PageController::class, 'contactSubmit']);
$router->get('/commandes-professionnelles', [PageController::class, 'pro']);
$router->post('/commandes-professionnelles', [PageController::class, 'proSubmit']);
$router->get('/faq', [PageController::class, 'faq']);
$router->get('/menu', [PageController::class, 'menu']);
$router->post('/preferences/theme', [PageController::class, 'theme']);
$router->post('/newsletter', [PageController::class, 'newsletter']);
$router->get('/newsletter/desinscription/{token:[a-f0-9]+}', [PageController::class, 'unsubscribe']);
foreach (['conditions-generales-de-vente', 'politique-de-confidentialite', 'politique-de-livraison', 'retours-et-remboursements', 'mentions-legales'] as $legal) {
    $router->get('/' . $legal, [PageController::class, 'legal']);
}
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/robots.txt', [SeoController::class, 'robots']);

// Espace client
$router->get('/compte/connexion', [AccountController::class, 'loginForm'], ['guest']);
$router->post('/compte/connexion', [AccountController::class, 'login'], ['guest']);
$router->get('/compte/inscription', [AccountController::class, 'registerForm'], ['guest']);
$router->post('/compte/inscription', [AccountController::class, 'register'], ['guest']);
$router->post('/compte/deconnexion', [AccountController::class, 'logout']);
$router->get('/compte/mot-de-passe-oublie', [AccountController::class, 'forgotForm']);
$router->post('/compte/mot-de-passe-oublie', [AccountController::class, 'forgot']);
$router->get('/compte/reinitialiser/{token:[a-f0-9]+}', [AccountController::class, 'resetForm']);
$router->post('/compte/reinitialiser/{token:[a-f0-9]+}', [AccountController::class, 'reset']);
$router->get('/compte', [AccountController::class, 'dashboard'], ['auth']);
$router->get('/compte/commandes', [AccountController::class, 'orders'], ['auth']);
$router->get('/compte/profil', [AccountController::class, 'profile'], ['auth']);
$router->post('/compte/profil', [AccountController::class, 'profileUpdate'], ['auth']);
$router->get('/compte/mot-de-passe', [AccountController::class, 'passwordForm'], ['auth']);
$router->post('/compte/mot-de-passe', [AccountController::class, 'passwordUpdate'], ['auth']);
$router->get('/compte/adresses', [AccountController::class, 'addresses'], ['auth']);
$router->post('/compte/adresses', [AccountController::class, 'addressStore'], ['auth']);
$router->post('/compte/adresses/{id:[0-9]+}/supprimer', [AccountController::class, 'addressDelete'], ['auth']);

// Administration
$router->get('/admin/connexion', [Admin\AuthController::class, 'form']);
$router->post('/admin/connexion', [Admin\AuthController::class, 'login']);
$router->post('/admin/deconnexion', [Admin\AuthController::class, 'logout'], ['staff']);
$router->get('/admin', [Admin\DashboardController::class, 'index'], ['staff']);

$router->get('/admin/produits', [Admin\ProductController::class, 'index'], ['perm:catalog.manage']);
$router->get('/admin/produits/nouveau', [Admin\ProductController::class, 'create'], ['perm:catalog.manage']);
$router->post('/admin/produits', [Admin\ProductController::class, 'store'], ['perm:catalog.manage']);
$router->get('/admin/produits/{id:[0-9]+}', [Admin\ProductController::class, 'edit'], ['perm:catalog.manage']);
$router->post('/admin/produits/{id:[0-9]+}', [Admin\ProductController::class, 'update'], ['perm:catalog.manage']);
$router->post('/admin/produits/{id:[0-9]+}/supprimer', [Admin\ProductController::class, 'delete'], ['perm:catalog.manage']);
$router->post('/admin/produits/{id:[0-9]+}/variantes', [Admin\ProductController::class, 'variantStore'], ['perm:catalog.manage']);
$router->post('/admin/variantes/{id:[0-9]+}', [Admin\ProductController::class, 'variantUpdate'], ['perm:catalog.manage']);
$router->post('/admin/variantes/{id:[0-9]+}/supprimer', [Admin\ProductController::class, 'variantDelete'], ['perm:catalog.manage']);
$router->post('/admin/produits/{id:[0-9]+}/images', [Admin\ProductController::class, 'imageStore'], ['perm:catalog.manage']);
$router->post('/admin/images/{id:[0-9]+}/supprimer', [Admin\ProductController::class, 'imageDelete'], ['perm:catalog.manage']);
$router->get('/admin/categories', [Admin\CategoryController::class, 'index'], ['perm:catalog.manage']);
$router->post('/admin/categories', [Admin\CategoryController::class, 'save'], ['perm:catalog.manage']);
$router->get('/admin/stock', [Admin\StockController::class, 'index'], ['perm:catalog.manage']);
$router->post('/admin/stock/{id:[0-9]+}', [Admin\StockController::class, 'adjust'], ['perm:catalog.manage']);
$router->get('/admin/promotions', [Admin\PromoController::class, 'index'], ['perm:catalog.manage']);
$router->post('/admin/promotions', [Admin\PromoController::class, 'save'], ['perm:catalog.manage']);

$router->get('/admin/commandes', [Admin\OrderController::class, 'index'], ['perm:orders.manage']);
$router->get('/admin/commandes/export', [Admin\OrderController::class, 'export'], ['perm:orders.manage']);
$router->get('/admin/commandes/{id:[0-9]+}', [Admin\OrderController::class, 'show'], ['perm:orders.manage']);
$router->post('/admin/commandes/{id:[0-9]+}/statut', [Admin\OrderController::class, 'transition'], ['perm:orders.manage']);
$router->post('/admin/commandes/{id:[0-9]+}/note', [Admin\OrderController::class, 'note'], ['perm:orders.manage']);
$router->post('/admin/commandes/{id:[0-9]+}/livraison', [Admin\OrderController::class, 'shipment'], ['perm:orders.manage']);
$router->get('/admin/devis', [Admin\QuoteController::class, 'index'], ['perm:orders.manage']);
$router->post('/admin/devis/{id:[0-9]+}', [Admin\QuoteController::class, 'update'], ['perm:orders.manage']);
$router->get('/admin/clients', [Admin\CustomerController::class, 'index'], ['perm:customers.view']);
$router->get('/admin/clients/{id:[0-9]+}', [Admin\CustomerController::class, 'show'], ['perm:customers.view']);

$router->get('/admin/messages', [Admin\ContentController::class, 'messages'], ['perm:content.manage']);
$router->post('/admin/messages/{id:[0-9]+}', [Admin\ContentController::class, 'messageUpdate'], ['perm:content.manage']);
$router->get('/admin/newsletter', [Admin\ContentController::class, 'newsletter'], ['perm:content.manage']);
$router->get('/admin/newsletter/export', [Admin\ContentController::class, 'newsletterExport'], ['perm:content.manage']);
$router->get('/admin/contenu', [Admin\ContentController::class, 'home'], ['perm:content.manage']);
$router->post('/admin/contenu', [Admin\ContentController::class, 'homeSave'], ['perm:content.manage']);
$router->get('/admin/pages', [Admin\ContentController::class, 'pages'], ['perm:content.manage']);
$router->get('/admin/pages/{id:[0-9]+}', [Admin\ContentController::class, 'pageEdit'], ['perm:content.manage']);
$router->post('/admin/pages/{id:[0-9]+}', [Admin\ContentController::class, 'pageSave'], ['perm:content.manage']);
$router->get('/admin/faq', [Admin\ContentController::class, 'faqs'], ['perm:content.manage']);
$router->post('/admin/faq', [Admin\ContentController::class, 'faqSave'], ['perm:content.manage']);
$router->post('/admin/faq/{id:[0-9]+}/supprimer', [Admin\ContentController::class, 'faqDelete'], ['perm:content.manage']);
$router->get('/admin/avis', [Admin\ContentController::class, 'reviews'], ['perm:content.manage']);
$router->post('/admin/avis/{id:[0-9]+}', [Admin\ContentController::class, 'reviewModerate'], ['perm:content.manage']);

$router->get('/admin/reglages', [Admin\SettingsController::class, 'index'], ['perm:settings.manage']);
$router->post('/admin/reglages', [Admin\SettingsController::class, 'save'], ['perm:settings.manage']);
$router->get('/admin/livraison', [Admin\SettingsController::class, 'zones'], ['perm:settings.manage']);
$router->post('/admin/livraison', [Admin\SettingsController::class, 'zoneSave'], ['perm:settings.manage']);
$router->get('/admin/integrations', [Admin\SettingsController::class, 'integrations'], ['perm:settings.manage']);
$router->get('/admin/equipe', [Admin\TeamController::class, 'index'], ['perm:users.manage']);
$router->post('/admin/equipe', [Admin\TeamController::class, 'store'], ['perm:users.manage']);
$router->post('/admin/equipe/{id:[0-9]+}', [Admin\TeamController::class, 'update'], ['perm:users.manage']);
$router->get('/admin/journal', [Admin\AuditController::class, 'index'], ['perm:audit.view']);
