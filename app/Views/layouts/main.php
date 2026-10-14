<?php
/** @var string $content */
use App\Core\Auth;
use App\Core\Session;
use App\Core\App;
use App\Services\CartService;
use App\Services\Settings;

$storeName = Settings::get('store_name');
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' — ' . $storeName : $storeName . ' — ' . Settings::get('store_tagline');
$metaDescription = $metaDescription ?? excerpt(Settings::get('home_hero_text'), 158);
$canonical = $canonical ?? null;
$ogImage = $ogImage ?? asset('img/og-default.png');
$cartCount = 0;
try {
    $cartCount = CartService::count(App::$request);
} catch (\Throwable) {
}
$user = Auth::user();
$flashes = Session::pullFlashes();
$path = App::$request?->path ?? '/';
$navItems = ['/boutique' => 'Boutique', '/a-propos' => 'À propos', '/commandes-professionnelles' => 'Professionnels', '/faq' => 'FAQ', '/contact' => 'Contact'];
$analytics = Settings::get('analytics_provider');
$wa = wa_link('Bonjour, je souhaite un renseignement sur vos produits.');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if ($canonical): ?><link rel="canonical" href="<?= e(abs_url($canonical)) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($storeName) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
<meta property="og:locale" content="fr_FR">
<?php if ($canonical): ?><meta property="og:url" content="<?= e(abs_url($canonical)) ?>"><?php endif; ?>
<meta property="og:image" content="<?= e(str_starts_with($ogImage, 'http') ? $ogImage : abs_url($ogImage)) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#2B1B0E">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/young-serif-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/figtree-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php foreach ($jsonLd ?? [] as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>"<?php if ($analytics !== 'none' && Settings::get('analytics_id') !== ''): ?> data-analytics="<?= e($analytics) ?>" data-analytics-id="<?= e(Settings::get('analytics_id')) ?>"<?php endif; ?>>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<?php if (is_demo_mode()): ?>
<div class="demo-banner" role="note">
  <div class="wrap"><strong>Site de démonstration.</strong> Produits, prix, stocks et illustrations sont fictifs et doivent être confirmés par <?= e($storeName) ?>. Aucun paiement réel n'est encaissé.</div>
</div>
<?php endif; ?>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="/" aria-label="<?= e($storeName) ?> — accueil">
      <svg class="brand-mark" viewBox="0 0 48 52" aria-hidden="true" focusable="false">
        <path d="M24 2 45 14v24L24 50 3 38V14z" class="brand-hex"/>
        <path d="M24 13c5 7.5 8.5 12.4 8.5 17.4a8.5 8.5 0 0 1-17 0C15.5 25.4 19 20.5 24 13z" class="brand-drop"/>
      </svg>
      <span class="brand-text"><span class="brand-name">Miel Pur</span><span class="brand-sub">de Côte d'Ivoire</span></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
      <span class="nav-toggle-bars" aria-hidden="true"></span><span class="nav-toggle-label">Menu</span>
    </button>
    <nav id="site-nav" class="site-nav" aria-label="Navigation principale">
      <ul>
        <?php foreach ($navItems as $href => $label): ?>
        <li><a href="<?= $href ?>"<?= str_starts_with($path, $href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <form class="header-search" action="/boutique" method="get" role="search">
        <label class="sr-only" for="header-q">Rechercher un produit</label>
        <input id="header-q" name="q" type="search" placeholder="Rechercher…" value="<?= e($path === '/boutique' ? (App::$request->query['q'] ?? '') : '') ?>" maxlength="80">
        <button type="submit" class="search-btn" aria-label="Lancer la recherche">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg>
        </button>
      </form>
    </nav>
    <div class="header-actions">
      <a class="header-link" href="<?= $user ? ($user['role'] === 'customer' ? '/compte' : '/admin') : '/compte/connexion' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
        <span class="header-link-label"><?= $user ? 'Mon compte' : 'Connexion' ?></span>
      </a>
      <a class="header-link cart-link" href="/panier">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20.5" r="1.2"/><circle cx="17" cy="20.5" r="1.2"/></svg>
        <span class="header-link-label">Panier</span>
        <span class="cart-count" data-cart-count aria-label="<?= (int) $cartCount ?> article(s)"><?= (int) $cartCount ?></span>
      </a>
    </div>
  </div>
</header>
<main id="contenu" tabindex="-1">
<?php if ($flashes): ?>
  <div class="wrap flashes" role="status" aria-live="polite">
  <?php foreach ($flashes as $type => $messages): foreach ($messages as $message): ?>
    <p class="flash flash-<?= e($type) ?>"><?= e($message) ?></p>
  <?php endforeach; endforeach; ?>
  </div>
<?php endif; ?>
<div class="sr-only" aria-live="polite" data-live-region></div>
<?= $content ?>
</main>
<footer class="site-footer">
  <div class="hex-rule" aria-hidden="true"></div>
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <p class="footer-title"><?= e($storeName) ?></p>
      <p><?= rich_inline(Settings::get('store_tagline')) ?>.</p>
      <p class="footer-contact">
        <?= rich_inline(Settings::get('store_city')) ?> — <?= rich_inline(Settings::get('store_address')) ?><br>
        Tél. : <?= rich_inline(Settings::get('store_phone')) ?><br>
        E-mail : <?= rich_inline(Settings::get('store_email')) ?><br>
        Horaires : <?= rich_inline(Settings::get('opening_hours')) ?>
      </p>
    </div>
    <div>
      <p class="footer-title">Boutique</p>
      <ul class="footer-links">
        <li><a href="/boutique">Tous les produits</a></li>
        <li><a href="/commandes-professionnelles">Commandes professionnelles</a></li>
        <li><a href="/suivi-commande">Suivre une commande</a></li>
        <li><a href="/faq">Questions fréquentes</a></li>
        <li><a href="/contact">Contact</a></li>
      </ul>
    </div>
    <div>
      <p class="footer-title">Informations</p>
      <ul class="footer-links">
        <li><a href="/conditions-generales-de-vente">Conditions générales de vente</a></li>
        <li><a href="/politique-de-livraison">Livraison</a></li>
        <li><a href="/retours-et-remboursements">Retours et remboursements</a></li>
        <li><a href="/politique-de-confidentialite">Confidentialité</a></li>
        <li><a href="/mentions-legales">Mentions légales</a></li>
      </ul>
    </div>
    <div>
      <p class="footer-title">Lettre d'information</p>
      <form class="newsletter-form" action="/newsletter" method="post">
        <?= csrf_field() ?>
        <label for="nl-email">Votre e-mail</label>
        <div class="inline-field">
          <input id="nl-email" name="email" type="email" required maxlength="190" autocomplete="email">
          <button class="btn btn-small" type="submit">S'inscrire</button>
        </div>
        <label class="check"><input type="checkbox" name="consent" value="1" required> J'accepte de recevoir des e-mails (désinscription à tout moment).</label>
        <div class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      </form>
      <?php $socials = array_filter(['Facebook' => Settings::get('social_facebook'), 'Instagram' => Settings::get('social_instagram'), 'TikTok' => Settings::get('social_tiktok')]); ?>
      <?php if ($socials): ?>
      <ul class="social-links">
        <?php foreach ($socials as $name => $href): ?><li><a href="<?= e($href) ?>" rel="noopener" target="_blank"><?= e($name) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
  <div class="wrap footer-bottom">
    <p>© <?= gmdate('Y') ?> <?= e($storeName) ?>. Prix en francs CFA (FCFA).</p>
    <p>Site réalisé par <strong>HELIX TECHNOLOGIES</strong>.</p>
  </div>
</footer>
<?php if ($wa): ?>
<a class="wa-float" href="<?= e($wa) ?>" rel="noopener" target="_blank" aria-label="Nous écrire sur WhatsApp">
  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3z"/><path d="M8.5 8.5c0 4 3 7 7 7l1-1.6-2-1-1 .8c-1.2-.5-2.2-1.5-2.7-2.7l.8-1-1-2z" class="wa-handset"/></svg>
</a>
<?php endif; ?>
<?php if ($analytics !== 'none' && Settings::get('analytics_id') !== ''): ?>
<div class="consent" data-consent hidden>
  <p>Nous aimerions mesurer l'audience du site (<?= e($analytics === 'ga4' ? 'Google Analytics' : 'Plausible') ?>). Acceptez-vous ?</p>
  <div class="consent-actions">
    <button class="btn btn-small" type="button" data-consent-accept>Accepter</button>
    <button class="btn btn-small btn-ghost" type="button" data-consent-refuse>Refuser</button>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
