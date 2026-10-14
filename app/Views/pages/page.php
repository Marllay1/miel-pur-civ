<?php
use App\Services\Settings;
/** @var array $page */
$identity = [];
if ($page['slug'] === 'mentions-legales') {
    foreach (['legal_company' => 'legal.company', 'legal_rccm' => 'legal.rccm', 'legal_ncc' => 'legal.ncc', 'legal_director' => 'legal.director', 'legal_host' => 'legal.host'] as $key => $label) {
        if (Settings::get($key) !== '') {
            $identity[t($label)] = Settings::get($key);
        }
    }
    $identity[t('legal.phone')] = \App\Services\SiteConfig::internationalPhone(Settings::get('store_phone'));
    $identity[t('legal.address')] = implode(', ', array_filter([Settings::get('store_address'), Settings::get('store_city'), Settings::get('store_country')]));
    if (Settings::get('store_email') !== '') {
        $identity[t('legal.email')] = Settings::get('store_email');
    }
}
?>
<div class="wrap narrow page-head">
  <h1><?= e($page['title']) ?></h1>
  <?php if ((int) $page['is_legal'] === 1): ?><p class="legal-meta"><?= e(t('legal.updated', ['date' => date_fr($page['updated_at'], false)])) ?></p><?php endif; ?>
</div>
<div class="wrap narrow">
  <div class="content-card">
    <div class="prose"><?= rich($page['body']) ?></div>
    <?php if ($identity): ?>
    <h2 class="group-title"><?= e(t('legal.identity')) ?></h2>
    <div class="group">
      <?php foreach (array_filter($identity) as $label => $value): ?>
      <div class="cell no-icon"><span class="cell-body cell-title"><?= e($label) ?></span><span class="cell-value"><?= e($value) ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($page['slug'] === 'a-propos' && ($aboutGallery = \App\Services\Media::aboutGallery())): ?>
  <h2 class="group-title"><?= e(t('about.gallery_title')) ?></h2>
  <div class="gallery gallery-about">
    <?php foreach ($aboutGallery as $g): ?>
    <figure class="gallery-item"><?= \App\Services\Media::img($g) ?><figcaption><span class="photo-credit"><?= e(t('photo.company')) ?></span></figcaption></figure>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
