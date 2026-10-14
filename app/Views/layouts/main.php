<?php
/** @var string $content */
use App\Core\App;
use App\Core\Auth;
use App\Core\Lang;
use App\Core\Session;
use App\Services\CartService;
use App\Services\Settings;

$storeName = Settings::get('store_name');
// Titre, description et image de partage : Paramètres du site → Référencement / Logos et images.
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' — ' . $storeName : \App\Services\SiteConfig::seoTitle();
$metaDescription = $metaDescription ?? \App\Services\SiteConfig::seoDescription();
$canonical = $canonical ?? null;
$ogImage = $ogImage ?? (Settings::get('og_image') !== '' ? Settings::get('og_image') : asset('img/og-default.jpg'));
$cartCount = 0;
try {
    $cartCount = CartService::count(App::$request);
} catch (\Throwable) {
}
$user = Auth::user();
// Lien vers l'administration : uniquement pour un membre de l'équipe authentifié (le serveur contrôle aussi chaque route).
$isStaff = Auth::isStaff();
$flashes = Session::pullFlashes();
$path = App::$request?->path ?? '/';
$query = App::$request?->query ?? [];
$navItems = ['/boutique' => t('nav.shop'), '/a-propos' => t('nav.about'), '/commandes-professionnelles' => t('nav.pro'), '/faq' => t('nav.faq'), '/contact' => t('nav.contact')];
$isActive =static fn (string $href) => $path === $href || str_starts_with($path, $href . '/');
$analytics = Settings::get('analytics_provider');
$wa = wa_link();
$socialLinks = social_links();
$other = Lang::get() === 'fr' ? 'en' : 'fr';
$here = $path . ($query ? '?' . http_build_query($query) : '');
if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    $here = '/'; // page de refus affichée sur une URL d'administration : aucun lien n'y renvoie
}
$altUrl = abs_url($here, $other);
// Langues proposées (Paramètres du site → Langues) ; seules les langues réellement traduites peuvent l'être.
$languages = \App\Services\SiteConfig::languages();
$jsI18n = [
    'added' => t('js.added'), 'viewCart' => t('js.view_cart'), 'error' => t('js.error'), 'sending' => t('js.sending'),
    'chooseZone' => t('checkout.choose_zone'), 'free' => t('checkout.free'), 'themeLight' => t('theme.light'), 'themeDark' => t('theme.dark'),
];
$tabs = [
    ['/', 'home', t('tab.home'), $path === '/'],
    ['/boutique', 'grid', t('tab.shop'), $isActive('/boutique')],
    ['/panier', 'bag', t('tab.cart'), $isActive('/panier') || $isActive('/commande')],
    [$user ? '/compte' : '/compte/connexion', 'person', t('tab.account'), $isActive('/compte')],
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
<?php if (isset($languages['en'])): ?><link rel="alternate" hreflang="en" href="<?= e(abs_url($canonical, 'en')) ?>"><?php endif; ?>
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
<meta name="theme-color" content="<?= e(Settings::get('color_background')) ?>" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#141210" media="(prefers-color-scheme: dark)">
<?= favicon_tags() ?><link rel="preload" href="/assets/fonts/figtree-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/figtree-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('vendor/aos/aos.css')) ?>">
<?php if (!empty($withMap)): ?><link rel="stylesheet" href="<?= e(asset('vendor/leaflet/leaflet.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_css_url()) ?>">
<?php foreach ($jsonLd ?? [] as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
</head>
<body class="site<?= isset($bodyClass) && $bodyClass !== '' ? ' ' . e($bodyClass) : '' ?>"<?php if ($analytics !== 'none' && Settings::get('analytics_id') !== ''): ?> data-analytics="<?= e($analytics) ?>" data-analytics-id="<?= e(Settings::get('analytics_id')) ?>"<?php endif; ?>>
<a class="skip-link" href="#contenu"><?= e(t('a11y.skip')) ?></a>
<header class="navbar" data-navbar>
  <div class="wrap navbar-inner">
    <a class="brand" href="/" aria-label="<?= e($storeName) ?> — <?= e(t('nav.home')) ?>">
      <?= brand_html() ?>
    </a>
    <nav aria-label="<?= e(t('nav.label')) ?>">
      <ul class="nav-links">
        <?php foreach ($navItems as $href => $label): ?>
        <li><a href="<?= $href ?>"<?= $isActive($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="nav-actions">
      <?php if ($isStaff): ?><a class="btn btn-small btn-admin" href="/admin" title="<?= e(t('nav.admin_hint')) ?>"><?= icon('gear') ?><span><?= e(t('nav.admin')) ?></span></a><?php endif; ?>
      <?php if (Settings::bool('social_in_header') && $socialLinks): ?>
      <ul class="header-social nav-only-desktop" aria-label="<?= e(t('footer.follow')) ?>">
        <?php foreach ($socialLinks as $network => [$href, $label]): ?><li><a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= social_icon($network) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (Settings::bool('header_show_search')): ?><a class="icon-btn" href="/boutique#recherche" aria-label="<?= e(t('nav.search')) ?>"><?= icon('search') ?></a><?php endif; ?>
      <?php if (count($languages) > 1): ?>
      <details class="lang-menu" data-lang-menu>
        <summary class="lang-toggle" aria-label="<?= e(t('lang.label')) ?> : <?= e($languages[Lang::get()] ?? 'Français') ?>"><?= icon('globe') ?><span><?= strtoupper(Lang::get()) ?></span><svg class="lang-caret" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M2.5 4.5 L6 8 L9.5 4.5"/></svg></summary>
        <ul class="lang-list">
          <?php foreach ($languages as $code => $name): ?>
          <li><a href="/langue/<?= $code ?>?retour=<?= e(rawurlencode($here)) ?>" hreflang="<?= $code ?>" lang="<?= $code ?>"<?= $code === Lang::get() ? ' aria-current="true"' : '' ?>><span><?= e($name) ?></span><span class="lang-code"><?= strtoupper($code) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endif; ?>
      <?php if (Settings::bool('header_show_theme')): ?>
      <form action="/preferences/theme" method="post" class="theme-form">
        <?= csrf_field() ?><input type="hidden" name="retour" value="<?= e(Lang::path($here)) ?>">
        <button class="icon-btn" type="submit" name="theme" value="<?= $theme === 'dark' ? 'light' : 'dark' ?>" data-theme-toggle aria-label="<?= e(t('nav.theme')) ?>">
          <span class="ti-moon"><?= icon('moon') ?></span><span class="ti-sun"><?= icon('sun') ?></span>
        </button>
      </form>
      <?php endif; ?>
      <a class="icon-btn nav-only-desktop" href="<?= $user ? '/compte' : '/compte/connexion' ?>" aria-label="<?= e($user ? t('nav.account') : t('nav.login')) ?>"><?= icon('person') ?></a>
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

<?php
// Coordonnées et paiements : source unique (Paramètres du site, Moyens de paiement) ; rien n'est affiché s'il n'est pas renseigné.
$footerPhones = \App\Services\SiteConfig::phones();
$footerEmails = \App\Services\SiteConfig::emails();
$footerAddress = \App\Services\SiteConfig::address();
$footerMaps = \App\Services\SiteConfig::mapsUrl();
$acceptedPayments = Settings::bool('footer_show_payments') ? \App\Services\PaymentMethods::acceptedLogos() : [];
?>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a class="footer-logo" href="/" aria-label="<?= e($storeName) ?> — <?= e(t('nav.home')) ?>"><?= brand_html('', true) ?></a>
      <?php if (setting('store_description') !== ''): ?><p><?= e(setting('store_description')) ?></p><?php endif; ?>
      <?php if ($socialLinks): ?>
      <ul class="social-links" aria-label="<?= e(t('footer.follow')) ?>">
        <?php foreach ($socialLinks as $network => [$href, $label]): ?>
        <li><a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= social_icon($network) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <div>
      <p class="footer-title"><?= e(t('footer.shop')) ?></p>
      <ul class="footer-links">
        <li><a href="/boutique"><?= e(t('footer.all_products')) ?></a></li>
        <li><a href="/a-propos"><?= e(t('nav.about')) ?></a></li>
        <li><a href="/commandes-professionnelles"><?= e(t('nav.pro')) ?></a></li>
        <li><a href="/suivi-commande"><?= e(t('footer.tracking')) ?></a></li>
        <li><a href="/faq"><?= e(t('nav.faq')) ?></a></li>
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
        <li><a href="/credits-photos"><?= e(t('footer.credits')) ?></a></li>
      </ul>
    </div>
    <div>
      <p class="footer-title"><?= e(t('footer.contact')) ?></p>
      <div class="footer-contact">
        <?php if ($footerAddress !== ''): ?><?php if ($footerMaps): ?><a href="<?= e($footerMaps) ?>" rel="noopener" target="_blank"><?= icon('pin') ?><?= e($footerAddress) ?></a><?php else: ?><span><?= icon('pin') ?><?= e($footerAddress) ?></span><?php endif; ?><?php endif; ?>
        <?php foreach ($footerPhones as $p): ?><a href="<?= e($p['href']) ?>"><?= icon('phone') ?><?= e($p['display']) ?></a><?php endforeach; ?>
        <?php foreach ($footerEmails as $email): ?><a href="mailto:<?= e($email) ?>"><?= icon('mail') ?><?= e($email) ?></a><?php endforeach; ?>
        <?php if (setting('opening_hours') !== ''): ?><span><?= icon('clock') ?><?= e(setting('opening_hours')) ?></span><?php endif; ?>
        <a href="/contact"><?= icon('chat') ?><?= e(t('footer.write_us')) ?></a>
      </div>
      <?php if (Settings::bool('footer_show_newsletter')): ?>
      <form class="newsletter-form" action="/newsletter" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="retour" value="<?= e(Lang::path($here)) ?>">
        <label class="footer-title" for="nl-email"><?= e(t('footer.newsletter')) ?></label>
        <div class="inline-field">
          <input id="nl-email" name="email" type="email" required maxlength="190" autocomplete="email" placeholder="<?= e(t('footer.newsletter_placeholder')) ?>">
          <button class="btn btn-small" type="submit"><?= e(t('footer.subscribe')) ?></button>
        </div>
        <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e(t('footer.newsletter_consent')) ?></span></label>
        <div class="hp" aria-hidden="true"><label><?= e(t('form.honeypot')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($acceptedPayments): ?>
  <div class="wrap footer-payments">
    <p class="footer-title"><?= e(t('footer.payments')) ?></p>
    <?= \App\Core\View::partial('payment-logos', ['methods' => $acceptedPayments]) ?>
  </div>
  <?php endif; ?>
  <div class="wrap footer-bottom">
    <p>© <?= gmdate('Y') ?> <?= e($storeName) ?></p>
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
<a class="wa-float" href="<?= e($wa) ?>" rel="noopener" target="_blank" aria-label="<?= e(t('wa.label')) ?>"><?= social_icon('whatsapp', 'wa-float-icon') ?></a>
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
<script src="<?= e(asset('vendor/aos/aos.js')) ?>" defer></script>
<?php if (!empty($withMap)): ?><script src="<?= e(asset('vendor/leaflet/leaflet.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
