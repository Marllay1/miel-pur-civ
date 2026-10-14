<?php
use App\Core\View;
use App\Services\PaymentService;
/** @var array $featured @var array $categories @var array $faqs */
$methods = PaymentService::availableMethods(null);
$catPhotos = ['miels' => 'fb-bouteilles-trio', 'cire-d-abeille' => 'wm-pains-cire', 'coffrets' => 'fb-stand-coffrets'];
$facebook = \App\Services\Settings::get('social_facebook');
$gallery = ['fb-bouteille-fond-jaune', 'fb-bouteilles-duo', 'wm-cadre-rayons'];
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <p class="eyebrow"><span class="dot"><?= icon('pin') ?></span><?= e(setting('store_city')) ?> · Côte d'Ivoire</p>
      <h1 class="display"><?= e(setting('home_hero_title')) ?></h1>
      <p class="lead"><?= e(setting('home_hero_text')) ?></p>
      <div class="btn-row">
        <a class="btn btn-large" href="/boutique"><?= e(t('home.cta_shop')) ?> <?= icon('arrow-right') ?></a>
        <a class="btn btn-large btn-tinted" href="/commandes-professionnelles"><?= e(t('home.cta_pro')) ?></a>
      </div>
      <ul class="trust">
        <li><?= icon('leaf') ?><?= e(t('home.trust_bio')) ?></li>
        <li><?= icon('shield') ?><?= e(t('home.trust_cert')) ?></li>
        <li><?= icon('truck') ?><?= e(t('home.trust_delivery')) ?></li>
      </ul>
    </div>
    <figure class="hero-visual hero-photo">
      <?= photo('fb-miel-en-rayon-vente', '', '(min-width: 960px) 50vw, 100vw', true) ?>
      <figcaption><?= photo_credit('fb-miel-en-rayon-vente') ?></figcaption>
    </figure>
  </div>
</section>

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

<?php if ($categories): ?>
<section class="section" aria-labelledby="h-cats">
  <div class="wrap">
    <div class="section-head"><h2 id="h-cats"><?= e(t('home.categories')) ?></h2></div>
    <div class="tiles">
      <?php foreach ($categories as $cat): ?>
      <a class="tile tile-photo" href="/boutique?categorie=<?= e($cat['slug']) ?>">
        <span class="tile-media"><?= isset($catPhotos[$cat['slug']]) ? photo($catPhotos[$cat['slug']], '', '(min-width: 960px) 33vw, 100vw') : '' ?><?php if ((photos()[$catPhotos[$cat['slug']] ?? '']['origin'] ?? '') === 'illustration'): ?><span class="media-badge"><?= e(t('photo.illustration_short')) ?></span><?php endif; ?></span>
        <span class="tile-body"><span class="tile-name"><?= e($cat['name']) ?></span><span class="tile-count"><?= e(t('home.products_count', ['count' => (int) $cat['product_count']])) ?></span></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" aria-labelledby="h-story">
  <div class="wrap story story-split">
    <figure class="story-photo">
      <?= photo('wm-apiculteurs-cameroun', '', '(min-width: 960px) 45vw, 100vw') ?>
      <figcaption><?= photo_credit('wm-apiculteurs-cameroun') ?></figcaption>
    </figure>
    <div class="story-card">
      <p class="eyebrow"><span class="dot"><?= icon('hex') ?></span><?= e(t('home.story_eyebrow')) ?></p>
      <h2 id="h-story"><?= e(setting('home_story_title')) ?></h2>
      <div class="prose"><?= rich(setting('home_story_text')) ?></div>
      <div class="cert"><?= icon('shield') ?><div><strong><?= e(t('home.cert_title')) ?></strong><br><span class="muted"><?= e(t('home.cert_text')) ?></span></div></div>
      <p class="btn-row"><a class="btn btn-tinted" href="/a-propos"><?= e(t('home.story_more')) ?></a></p>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="h-gallery">
  <div class="wrap">
    <div class="section-head">
      <div><h2 id="h-gallery"><?= e(t('home.gallery_title')) ?></h2><p><?= e(t('home.gallery_sub')) ?></p></div>
      <?php if ($facebook !== ''): ?><a class="see-all" href="<?= e($facebook) ?>" rel="noopener" target="_blank"><?= e(t('home.follow_fb')) ?><?= icon('chevron') ?></a><?php endif; ?>
    </div>
    <div class="gallery">
      <?php foreach ($gallery as $g): ?><figure class="gallery-item"><?= photo($g, '', '(min-width: 960px) 33vw, 100vw') ?><figcaption><?= photo_credit($g) ?></figcaption></figure><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="h-steps">
  <div class="wrap">
    <div class="section-head"><h2 id="h-steps"><?= e(t('home.steps_title')) ?></h2></div>
    <ol class="steps">
      <li><strong><?= e(t('home.step1')) ?></strong><span><?= e(t('home.step1_text')) ?></span></li>
      <li><strong><?= e(t('home.step2')) ?></strong><span><?= e(t('home.step2_text')) ?></span></li>
      <li><strong><?= e(t('home.step3')) ?></strong><span><?= e(t('home.step3_text')) ?></span></li>
      <li><strong><?= e(t('home.step4')) ?></strong><span><?= e(t('home.step4_text')) ?></span></li>
    </ol>
    <?php if ($methods): ?>
    <h3 class="group-title"><?= e(t('home.payment_methods')) ?></h3>
    <div class="pay-strip">
      <?php foreach ($methods as $m): ?><span class="pay-badge"><?= pay_logo($m['code']) ?><?= e($m['label']) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="section" aria-labelledby="h-pro">
  <div class="wrap">
    <div class="pro-card">
      <div>
        <h2 id="h-pro"><?= e(t('home.pro_title')) ?></h2>
        <p><?= e(setting('home_pro_text')) ?></p>
      </div>
      <a class="btn btn-large" href="/commandes-professionnelles"><?= e(t('home.pro_cta')) ?></a>
    </div>
  </div>
</section>

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
