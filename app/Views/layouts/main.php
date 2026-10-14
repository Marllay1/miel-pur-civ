<?php
/** @var string $content */
use App\Core\App;
use App\Core\Auth;
use App\Core\Lang;
use App\Core\Session;
use App\Services\CartService;
use App\Services\Settings;

$storeName = Settings::get('store_name');
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' — ' . $storeName : $storeName . ' — ' . setting('store_tagline');
$metaDescription = $metaDescription ?? excerpt(setting('home_hero_text'), 158);
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
$query = App::$request?->query ?? [];
$navItems = ['/boutique' => t('nav.shop'), '/a-propos' => t('nav.about'), '/commandes-professionnelles' => t('nav.pro'), '/faq' => t('nav.faq'), '/contact' => t('nav.contact')];
$isActive = static fn (string $href) => $path === $href || str_starts_with($path, $href . '/');
$analytics = Settings::get('analytics_provider');
$wa = wa_link(t('wa.greeting'));
$other = Lang::get() === 'fr' ? 'en' : 'fr';
$here = $path . ($query ? '?' . http_build_query($query) : '');
$altUrl = abs_url($here, $other);
$jsI18n = [
    'added' => t('js.added'), 'viewCart' => t('js.view_cart'), 'error' => t('js.error'), 'sending' => t('js.sending'),
    'chooseZone' => t('checkout.choose_zone'), 'free' => t('checkout.free'), 'themeLight' => t('theme.light'), 'themeDark' => t('theme.dark'),
];
$tabs = [
    ['/', 'home', t('tab.home'), $path === '/'],
    ['/boutique', 'grid', t('tab.shop'), $isActive('/boutique')],
    ['/panier', 'bag', t('tab.cart'), $isActive('/panier') || $isActive('/commande')],
    [$user ? ($user['role'] === 'customer' ? '/compte' : '/admin') : '/compte/connexion', 'person', t('tab.account'), $isActive('/compte')],
    ['/menu', 'more', t('tab.more'), $isActive('/menu')],
];
?><!doctype html>
<html lang="<?= e(Lang::get()) ?>"<?= $theme ? ' data-theme="' . e($theme) . '"' : '' ?> data-i18n="<?= e(json_encode($jsI18n, JSON_UNESCAPED_UNICODE)) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if ($canonical): ?>
<link rel="canonical" href="<?= e(abs_url($canonical)) ?>">
<link rel="alternate" hreflang="fr" href="<?= e(abs_url($canonical, 'fr')) ?>">
<link rel="alternate" hreflang="en" href="<?= e(abs_url($canonical, 'en')) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(abs_url($canonical, 'fr')) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($storeName) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
<meta property="og:locale" content="<?= Lang::get() === 'en' ? 'en_GB' : 'fr_FR' ?>">
<?php if ($canonical): ?><meta property="og:url" content="<?= e(abs_url($canonical)) ?>"><?php endif; ?>
<meta property="og:image" content="<?= e(str_starts_with($ogImage, 'http') ? $ogImage : base_url() . $ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#F5F2EC" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#141210" media="(prefers-color-scheme: dark)">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<link rel="preload" href="/assets/fonts/figtree-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/figtree-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php foreach ($jsonLd ?? [] as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>"<?php if ($analytics !== 'none' && Settings::get('analytics_id') !== ''): ?> data-analytics="<?= e($analytics) ?>" data-analytics-id="<?= e(Settings::get('analytics_id')) ?>"<?php endif; ?>>
<a class="skip-link" href="#contenu"><?= e(t('a11y.skip')) ?></a>
<header class="navbar" data-navbar>
  <div class="wrap navbar-inner">
    <a class="brand" href="/" aria-label="<?= e($storeName) ?> — <?= e(t('nav.home')) ?>">
      <svg class="brand-mark" viewBox="0 0 48 52" aria-hidden="true" focusable="false"><path class="hex" d="M24 1.5 46 14v24L24 50.5 2 38V14z"/><path class="drop" d="M24 12.5c5.2 7.6 8.8 12.6 8.8 17.8a8.8 8.8 0 0 1-17.6 0c0-5.2 3.6-10.2 8.8-17.8z"/></svg>
      <span class="brand-text"><span class="brand-name">Miel Pur</span><span class="brand-sub">de Côte d'Ivoire</span></span>
    </a>
    <nav aria-label="<?= e(t('nav.label')) ?>">
      <ul class="nav-links">
        <?php foreach ($navItems as $href => $label): ?>
        <li><a href="<?= $href ?>"<?= $isActive($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="nav-actions">
      <a class="icon-btn" href="/boutique#recherche" aria-label="<?= e(t('nav.search')) ?>"><?= icon('search') ?></a>
      <a class="icon-btn lang-btn" href="<?= e($altUrl) ?>" hreflang="<?= $other ?>" lang="<?= $other ?>" aria-label="<?= e(t('nav.switch_lang')) ?>"><?= strtoupper($other) ?></a>
      <form action="/preferences/theme" method="post" class="theme-form">
        <?= csrf_field() ?><input type="hidden" name="retour" value="<?= e(Lang::path($here)) ?>">
        <button class="icon-btn" type="submit" name="theme" value="<?= $theme === 'dark' ? 'light' : 'dark' ?>" data-theme-toggle aria-label="<?= e(t('nav.theme')) ?>">
          <span class="ti-moon"><?= icon('moon') ?></span><span class="ti-sun"><?= icon('sun') ?></span>
        </button>
      </form>
      <a class="icon-btn nav-only-desktop" href="<?= $user ? ($user['role'] === 'customer' ? '/compte' : '/admin') : '/compte/connexion' ?>" aria-label="<?= e($user ? t('nav.account') : t('nav.login')) ?>"><?= icon('person') ?></a>
      <a class="icon-btn nav-only-desktop" href="/panier" aria-label="<?= e(t('nav.cart_count', ['count' => (int) $cartCount])) ?>">
        <?= icon('bag') ?><span class="badge-count" data-cart-count data-count="<?= (int) $cartCount ?>"><?= (int) $cartCount ?></span>
      </a>
    </div>
  </div>
</header>

<main id="contenu" tabindex="-1">
<?php if (is_demo_mode()): ?>
  <div class="wrap"><p class="notice notice-amber small"><?= icon('info') ?> <?= e(t('demo.banner')) ?></p></div>
<?php endif; ?>
<?php if ($flashes): ?>
  <div class="wrap flashes" role="status" aria-live="polite">
  <?php foreach ($flashes as $type => $messages): foreach ($messages as $message): ?>
    <p class="flash flash-<?= e($type) ?>"><?= icon($type === 'success' ? 'check' : ($type === 'error' ? 'info' : 'info')) ?><span><?= e($message) ?></span></p>
  <?php endforeach; endforeach; ?>
  </div>
<?php endif; ?>
<div class="sr-only" aria-live="polite" data-live-region></div>
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <p class="footer-title"><?= e($storeName) ?></p>
      <p><?= e(setting('store_tagline')) ?></p>
      <div class="footer-contact">
        <span><?= icon('pin') ?><?= e(trim(Settings::get('store_address') !== '' ? Settings::get('store_address') . ', ' . Settings::get('store_city') : Settings::get('store_city') . ', Côte d\'Ivoire')) ?></span>
        <?php if (Settings::get('store_phone') !== ''): ?><a href="<?= e(tel_link(Settings::get('store_phone'))) ?>"><?= icon('phone') ?><?= e(Settings::get('store_phone')) ?></a><?php endif; ?>
        <?php if (Settings::get('store_email') !== ''): ?><a href="mailto:<?= e(Settings::get('store_email')) ?>"><?= icon('mail') ?><?= e(Settings::get('store_email')) ?></a><?php endif; ?>
        <?php if (setting('opening_hours') !== ''): ?><span><?= icon('clock') ?><?= e(setting('opening_hours')) ?></span><?php endif; ?>
      </div>
    </div>
    <div>
      <p class="footer-title"><?= e(t('footer.shop')) ?></p>
      <ul class="footer-links">
        <li><a href="/boutique"><?= e(t('footer.all_products')) ?></a></li>
        <li><a href="/commandes-professionnelles"><?= e(t('nav.pro')) ?></a></li>
        <li><a href="/suivi-commande"><?= e(t('footer.tracking')) ?></a></li>
        <li><a href="/faq"><?= e(t('nav.faq')) ?></a></li>
        <li><a href="/contact"><?= e(t('nav.contact')) ?></a></li>
      </ul>
    </div>
    <div>
      <p class="footer-title"><?= e(t('footer.info')) ?></p>
      <ul class="footer-links">
        <li><a href="/conditions-generales-de-vente"><?= e(t('footer.terms')) ?></a></li>
        <li><a href="/politique-de-livraison"><?= e(t('footer.delivery')) ?></a></li>
        <li><a href="/retours-et-remboursements"><?= e(t('footer.returns')) ?></a></li>
        <li><a href="/politique-de-confidentialite"><?= e(t('footer.privacy')) ?></a></li>
        <li><a href="/mentions-legales"><?= e(t('footer.legal')) ?></a></li>
      </ul>
    </div>
    <div>
      <p class="footer-title"><?= e(t('footer.newsletter')) ?></p>
      <form class="newsletter-form" action="/newsletter" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="retour" value="<?= e(Lang::path($here)) ?>">
        <label class="sr-only" for="nl-email"><?= e(t('field.email')) ?></label>
        <div class="inline-field">
          <input id="nl-email" name="email" type="email" required maxlength="190" autocomplete="email" placeholder="<?= e(t('footer.newsletter_placeholder')) ?>">
          <button class="btn btn-small" type="submit"><?= e(t('footer.subscribe')) ?></button>
        </div>
        <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e(t('footer.newsletter_consent')) ?></span></label>
        <div class="hp" aria-hidden="true"><label><?= e(t('form.honeypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
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
    <p>© <?= gmdate('Y') ?> <?= e($storeName) ?> · <?= e(t('footer.prices')) ?></p>
    <p><?= e(t('footer.made_by')) ?> <strong>HELIX TECHNOLOGIES</strong></p>
  </div>
</footer>

<nav class="tabbar" aria-label="<?= e(t('nav.tabs')) ?>">
  <ul>
    <?php foreach ($tabs as [$href, $ic, $label, $active]): ?>
    <li><a href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= e($label) ?></span><?php if ($ic === 'bag'): ?><span class="badge-count" data-cart-count data-count="<?= (int) $cartCount ?>"><?= (int) $cartCount ?></span><?php endif; ?></a></li>
    <?php endforeach; ?>
  </ul>
</nav>

<?php if ($wa): ?>
<a class="wa-float" href="<?= e($wa) ?>" rel="noopener" target="_blank" aria-label="<?= e(t('wa.label')) ?>"><?= icon('chat') ?></a>
<?php endif; ?>
<div class="toast" data-toast role="status" aria-live="polite"></div>
<?php if ($analytics !== 'none' && Settings::get('analytics_id') !== ''): ?>
<div class="consent" data-consent hidden>
  <p><?= e(t('consent.text', ['tool' => $analytics === 'ga4' ? 'Google Analytics' : 'Plausible'])) ?></p>
  <div class="btn-row">
    <button class="btn btn-small" type="button" data-consent-accept><?= e(t('consent.accept')) ?></button>
    <button class="btn btn-small btn-gray" type="button" data-consent-refuse><?= e(t('consent.refuse')) ?></button>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
