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
    'Ventes' => [['/admin/commandes', 'Commandes', 'orders.manage'], ['/admin/devis', 'Devis professionnels', 'orders.manage'], ['/admin/clients', 'Clients', 'customers.view']],
    'Catalogue' => [['/admin/produits', 'Produits', 'catalog.manage'], ['/admin/categories', 'Catégories', 'catalog.manage'], ['/admin/stock', 'Stock', 'catalog.manage'], ['/admin/promotions', 'Codes promo', 'catalog.manage']],
    'Contenus' => [['/admin/contenu', 'Accueil', 'content.manage'], ['/admin/pages', 'Pages et légal', 'content.manage'], ['/admin/faq', 'FAQ', 'content.manage'], ['/admin/avis', 'Avis', 'content.manage'], ['/admin/messages', 'Messages', 'content.manage'], ['/admin/newsletter', 'Newsletter', 'content.manage']],
    'Configuration' => [['/admin/reglages', 'Réglages', 'settings.manage'], ['/admin/livraison', 'Zones de livraison', 'settings.manage'], ['/admin/integrations', 'Intégrations', 'settings.manage'], ['/admin/equipe', 'Équipe et rôles', 'users.manage'], ['/admin/journal', 'Journal d\'audit', 'audit.view']],
];
$isCurrent = static fn (string $href) => $href === '/admin' ? $path === '/admin' : ($path === $href || str_starts_with($path, $href . '/'));
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($pageTitle ?? 'Administration') . ' — Admin ' . Settings::get('store_name')) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="admin-top">
  <a class="brand" href="/admin">
    <svg class="brand-mark" viewBox="0 0 48 52" aria-hidden="true" focusable="false"><path d="M24 2 45 14v24L24 50 3 38V14z" class="brand-hex"/><path d="M24 13c5 7.5 8.5 12.4 8.5 17.4a8.5 8.5 0 0 1-17 0C15.5 25.4 19 20.5 24 13z" class="brand-drop"/></svg>
    <span class="brand-text"><span class="brand-name">Miel Pur</span><span class="brand-sub">Administration</span></span>
  </a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="admin-nav"><span class="nav-toggle-bars" aria-hidden="true"></span><span class="nav-toggle-label">Menu</span></button>
  <div class="admin-user">
    <?php if ($user): ?>
    <span><?= e($user['first_name'] . ' ' . $user['last_name']) ?> · <span class="muted"><?= e($user['role']) ?></span></span>
    <a href="/" target="_blank" rel="noopener">Voir le site</a>
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
