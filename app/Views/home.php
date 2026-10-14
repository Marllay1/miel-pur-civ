<?php
use App\Core\View;
use App\Services\PaymentService;
/** @var array $featured @var array $categories @var array $faqs */
$methods = PaymentService::availableMethods(null);
$catIcons = ['miels' => 'hex', 'cire-d-abeille' => 'box', 'coffrets' => 'star'];
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
    <div class="hero-visual">
      <div class="hero-card">
        <img src="<?= e(asset('img/hero.svg')) ?>" alt="<?= e(t('home.hero_alt')) ?>" width="640" height="640">
        <span class="glass-chip chip-a"><?= icon('leaf') ?><?= e(t('home.chip_bio')) ?></span>
        <span class="glass-chip chip-c"><?= icon('shield') ?><?= e(t('home.chip_cert')) ?></span>
        <span class="glass-chip chip-b"><?= icon('pin') ?><?= e(t('home.chip_origin')) ?></span>
      </div>
    </div>
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
      <a class="tile" href="/boutique?categorie=<?= e($cat['slug']) ?>">
        <span class="cell-icon"><?= icon($catIcons[$cat['slug']] ?? 'hex') ?></span>
        <span><span class="tile-name"><?= e($cat['name']) ?></span><br><span class="tile-count"><?= e(t('home.products_count', ['count' => (int) $cat['product_count']])) ?></span></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" aria-labelledby="h-story">
  <div class="wrap story">
    <div class="story-card">
      <p class="eyebrow"><span class="dot"><?= icon('hex') ?></span><?= e(t('home.story_eyebrow')) ?></p>
      <h2 id="h-story"><?= e(setting('home_story_title')) ?></h2>
      <div class="prose"><?= rich(setting('home_story_text')) ?></div>
      <div class="cert"><?= icon('shield') ?><div><strong><?= e(t('home.cert_title')) ?></strong><br><span class="muted"><?= e(t('home.cert_text')) ?></span></div></div>
      <p class="btn-row"><a class="btn btn-tinted" href="/a-propos"><?= e(t('home.story_more')) ?></a></p>
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
