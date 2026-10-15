<?php
use App\Core\View;
use App\Services\Settings;
/** @var array $featured @var array $categories @var array $faqs @var array $slides */
$heroImage = Settings::get('home_hero_image');
// Logos des moyens de paiement réellement proposés (masqués par défaut : Paramètres du site → Page d'accueil).
$acceptedPayments = Settings::bool('home_show_payments') ? \App\Services\PaymentMethods::acceptedLogos() : [];
$facebook = Settings::get('social_facebook');
// Photos choisies dans Administration → Photos de l'entreprise (aucune image codée en dur).
$gallery = Settings::bool('home_show_gallery') ? \App\Services\Media::homeGallery() : [];
$story = \App\Services\Media::story();
$categories = Settings::bool('home_show_categories') ? array_values(array_filter($categories, static fn (array $c) => $c['parent_id'] === null)) : [];
$featured = Settings::bool('home_show_featured') ? $featured : [];
$faqs = Settings::bool('home_show_faq') ? $faqs : [];
// Blocs de réassurance (Administration → Blocs de réassurance).
$highlights = Settings::bool('home_show_highlights') ? \App\Services\HomeHighlights::visible() : [];
?>
<?php if ($slides): ?>
<h1 class="sr-only"><?= e(setting('home_hero_title')) ?></h1>
<?= View::partial('carousel', ['slides' => $slides]) ?>
<?php else: ?>
<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <?php if (Settings::bool('header_show_tagline')): ?><p class="eyebrow"><?= e(setting('store_tagline')) ?></p><?php endif; ?>
      <h1 class="display"><?= e(setting('home_hero_title')) ?></h1>
      <p class="lead"><?= e(setting('home_hero_text')) ?></p>
    </div>
    <?php if ($heroImage !== ''): ?>
    <figure class="hero-visual hero-photo"><img src="<?= e($heroImage) ?>" alt="" fetchpriority="high" decoding="async"></figure>
    <?php elseif ($story !== null): ?>
    <figure class="hero-visual hero-photo"><?= \App\Services\Media::img($story, '', true) ?></figure>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
<?php if ($highlights): ?>
<section class="trust-band" aria-label="<?= e(t('home.trust_label')) ?>">
  <ul class="wrap trust-row">
    <?php foreach ($highlights as $h): ?>
    <li><span class="highlight-icon" aria-hidden="true"><?= icon((string) $h['icon']) ?></span>
      <?php if ((string) $h['link_url'] !== ''): ?><a href="<?= e((string) $h['link_url']) ?>"<?= str_starts_with((string) $h['link_url'], 'https://') ? ' rel="noopener"' : '' ?>><?php endif; ?>
      <span><strong><?= e($h['title']) ?></strong><?php if ((string) $h['body'] !== ''): ?><span class="trust-text"><?= e($h['body']) ?></span><?php endif; ?></span>
      <?php if ((string) $h['link_url'] !== ''): ?></a><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section" aria-labelledby="h-featured">
  <div class="wrap">
    <div class="section-head">
      <div><h2 id="h-featured"><?= e(t('home.featured')) ?></h2><p><?= e(t('home.featured_sub')) ?></p></div>
      <a class="see-all" href="/boutique"><?= e(t('home.see_all')) ?><?= icon('chevron') ?></a>
    </div>
    <div class="product-rail">
      <?php foreach ($featured as $product): ?><?= View::partial('product-card', ['product' => $product]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php // « Demander un devis » : texte et lien dans Paramètres du site → Page d'accueil.
$quoteLink = Settings::get('cta_quote_link') !== '' ? Settings::get('cta_quote_link') : '/commandes-professionnelles';
$quoteLabel = trim(Settings::localized('cta_quote_label')) !== '' ? Settings::localized('cta_quote_label') : t('home.pro_cta');
if (Settings::bool('home_show_pro')): ?>
<section class="section" aria-labelledby="h-pro">
  <div class="wrap">
    <div class="pro-card">
      <div>
        <h2 id="h-pro"><?= e(t('home.pro_title')) ?></h2>
        <p><?= e(setting('home_pro_text')) ?></p>
      </div>
      <a class="btn" href="<?= e($quoteLink) ?>"><?= e($quoteLabel) ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($categories): ?>
<section class="section" aria-labelledby="h-cats">
  <div class="wrap">
    <div class="section-head"><h2 id="h-cats"><?= e(t('home.categories')) ?></h2></div>
    <div class="tiles">
      <?php foreach ($categories as $cat): ?>
      <a class="tile tile-photo" href="/boutique?categorie=<?= e($cat['slug']) ?>">
        <span class="tile-media"><?php if (!empty($cat['image_path'])): ?><img src="<?= e(\App\Services\ImageService::small((string) $cat['image_path'])) ?>"<?= img_srcset((string) $cat['image_path'], '(min-width: 960px) 33vw, 100vw') ?> alt="" loading="lazy" decoding="async"><?php else: ?><span class="media-placeholder" aria-hidden="true"><?= e(mb_substr($cat['name'], 0, 1)) ?></span><?php endif; ?></span>
        <span class="tile-body"><span class="tile-name"><?= e($cat['name']) ?></span><span class="tile-count"><?= e(t('home.products_count', ['count' => (int) $cat['product_count']])) ?></span></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (Settings::bool('home_show_story')): ?>
<section class="section" aria-labelledby="h-story">
  <div class="wrap story<?= $story !== null ? ' story-split' : '' ?>">
    <?php if ($story !== null): ?>
    <figure class="story-photo"><?= \App\Services\Media::img($story) ?><figcaption><span class="photo-credit"><?= e(t('photo.company')) ?></span></figcaption></figure>
    <?php endif; ?>
    <div class="story-card">
      <p class="eyebrow"><?= e(t('home.story_eyebrow')) ?></p>
      <h2 id="h-story"><?= e(setting('home_story_title')) ?></h2>
      <div class="prose"><?= rich(setting('home_story_text')) ?></div>
      <div class="cert"><?= icon('shield') ?><div><strong><?= e(t('home.cert_title')) ?></strong><br><span class="muted"><?= e(t('home.cert_text')) ?></span></div></div>
      <p class="btn-row"><a class="btn btn-tinted" href="/a-propos"><?= e(t('home.story_more')) ?></a></p>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($gallery): ?>
<section class="section" aria-labelledby="h-gallery">
  <div class="wrap">
    <div class="section-head">
      <div><h2 id="h-gallery"><?= e(t('home.gallery_title')) ?></h2><p><?= e(t('home.gallery_sub')) ?></p></div>
      <?php if ($facebook !== ''): ?><a class="see-all" href="<?= e($facebook) ?>" rel="noopener" target="_blank"><?= e(t('home.follow_fb')) ?><?= icon('chevron') ?></a><?php endif; ?>
    </div>
    <div class="gallery">
      <?php foreach ($gallery as $g): ?><figure class="gallery-item"><?= \App\Services\Media::img($g) ?><figcaption><span class="photo-credit"><?= e(t('photo.company')) ?></span></figcaption></figure><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (Settings::bool('home_show_steps')): ?>
<section class="section" aria-labelledby="h-steps">
  <div class="wrap">
    <div class="section-head"><h2 id="h-steps"><?= e(t('home.steps_title')) ?></h2></div>
    <ol class="steps">
      <li><strong><?= e(t('home.step1')) ?></strong><span><?= e(t('home.step1_text')) ?></span></li>
      <li><strong><?= e(t('home.step2')) ?></strong><span><?= e(t('home.step2_text')) ?></span></li>
      <li><strong><?= e(t('home.step3')) ?></strong><span><?= e(t('home.step3_text')) ?></span></li>
      <li><strong><?= e(t('home.step4')) ?></strong><span><?= e(t('home.step4_text')) ?></span></li>
    </ol>
    <?php if ($acceptedPayments): ?>
    <h3 class="group-title"><?= e(t('home.payment_methods')) ?></h3>
    <?= View::partial('payment-logos', ['methods' => $acceptedPayments]) ?>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($faqs): ?>
<section class="section" aria-labelledby="h-faq">
  <div class="wrap narrow">
    <div class="section-head">
      <h2 id="h-faq"><?= e(t('faq.title')) ?></h2>
      <a class="see-all" href="/faq"><?= e(t('home.see_all')) ?><?= icon('chevron') ?></a>
    </div>
    <div class="disclosure">
      <?php foreach ($faqs as $f): ?>
      <details>
        <summary><?= e($f['question']) ?><?= icon('chevron') ?></summary>
        <div class="prose"><?= rich($f['answer']) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
