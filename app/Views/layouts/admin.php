<?php
/** @var string $content */
use App\Core\App;
use App\Core\Auth;
use App\Core\Session;
use App\Services\Settings;

$user = Auth::user();
$path = App::$request?->path ?? '/admin';
$flashes = Session::pullFlashes();
$sections = [
    'Pilotage' => [['/admin', 'Tableau de bord', null]],
    'Catalogue' => [['/admin/produits', 'Produits', 'catalog.manage'], ['/admin/categories', 'Catégories', 'catalog.manage'], ['/admin/stock', 'Stock', 'catalog.manage'], ['/admin/promotions', 'Codes promo', 'catalog.manage']],
    'Ventes' => [['/admin/commandes', 'Commandes', 'orders.manage'], ['/admin/paiements/journal', 'Journal des paiements', 'orders.manage'], ['/admin/professionnels', 'Clients professionnels', 'orders.manage'], ['/admin/devis', 'Devis professionnels', 'orders.manage'], ['/admin/clients', 'Clients', 'customers.view']],
    'Site public' => [['/admin/parametres/accueil', 'Page d\'accueil', 'settings.manage'], ['/admin/carrousel', 'Carrousel de l\'accueil', 'content.manage'], ['/admin/reassurance', 'Blocs de réassurance', 'content.manage'], ['/admin/photos', 'Photos de l\'entreprise', 'content.manage'], ['/admin/textes', 'Textes du site', 'content.manage'],['/admin/contenu', 'Textes de l\'accueil', 'content.manage'], ['/admin/pages', 'Pages et légal', 'content.manage'], ['/admin/faq', 'FAQ', 'content.manage'], ['/admin/avis', 'Avis', 'content.manage'], ['/admin/messages', 'Messages', 'content.manage'], ['/admin/newsletter', 'Newsletter', 'content.manage']],
    'Paramètres du site' => [['/admin/parametres', 'Vue d\'ensemble', 'settings.manage'], ['/admin/parametres/identite', 'Identité', 'settings.manage'], ['/admin/parametres/logos', 'Logos et images', 'settings.manage'], ['/admin/parametres/coordonnees', 'Coordonnées', 'settings.manage'], ['/admin/parametres/reseaux', 'Réseaux sociaux', 'settings.manage'], ['/admin/parametres/apparence', 'Apparence', 'settings.manage'], ['/admin/parametres/langues', 'Langues', 'settings.manage'], ['/admin/paiements', 'Moyens de paiement', 'settings.manage'], ['/admin/parametres/paiement', 'Paramètres de paiement', 'settings.manage'], ['/admin/livraison', 'Zones de livraison', 'settings.manage'], ['/admin/devises', 'Devises', 'settings.manage'], ['/admin/parametres/commerce', 'Règles de commande', 'settings.manage'], ['/admin/parametres/referencement', 'Référencement', 'settings.manage'], ['/admin/parametres/mentions', 'Mentions légales', 'settings.manage'], ['/admin/integrations', 'Intégrations', 'settings.manage']],
    'Administration' => [['/admin/equipe', 'Équipe', 'users.manage'], ['/admin/journal', 'Journal d\'audit', 'audit.view']],
];
$isCurrent = static fn (string $href) => in_array($href, ['/admin', '/admin/parametres'], true) ? $path === $href
    : ($path === $href || (str_starts_with($path, $href . '/') && !($href === '/admin/paiements' && str_starts_with($path, '/admin/paiements/journal'))));
?><!doctype html>
<html lang="fr"<?= !empty($theme) ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($pageTitle ?? 'Administration') . ' — Admin ' . Settings::get('store_name')) ?></title>
<?= favicon_tags() ?><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="admin-top">
  <a class="brand" href="/admin">
    <?= brand_html('Administration') ?>
  </a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="admin-nav"><span class="nav-toggle-bars" aria-hidden="true"></span><span class="nav-toggle-label">Menu</span></button>
  <div class="admin-user">
    <?php if ($user): ?>
    <a class="btn btn-small btn-gray" href="/">Voir le site</a>
    <form action="/admin/deconnexion" method="post"><?= csrf_field() ?><button class="link-btn" type="submit">Déconnexion</button></form>
    <?php endif; ?>
  </div>
</header>
<div class="admin-shell">
  <nav id="admin-nav" class="admin-nav" aria-label="Administration">
    <?php foreach ($sections as $title => $links):
        $visible = array_filter($links, static fn ($l) => $l[2] === null || Auth::can($l[2]));
        if (!$visible) { continue; } ?>
    <p class="admin-nav-title"><?= e($title) ?></p>
    <ul>
      <?php foreach ($visible as [$href, $label]): ?>
      <li><a href="<?= $href ?>"<?= $isCurrent($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
  </nav>
  <main id="contenu" class="admin-main" tabindex="-1">
    <?php foreach ($flashes as $type => $messages): foreach ($messages as $m): ?>
    <p class="flash flash-<?= e($type) ?>" role="status"><?= e($m) ?></p>
    <?php endforeach; endforeach; ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
