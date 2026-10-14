<?php
use App\Services\Settings;
$facebook = Settings::get('social_facebook');
// Photos de l'entreprise : photothèque administrable ; aucune photo d'illustration n'est plus utilisée.
$company = \App\Services\Media::all();
$illustrations = array_filter(photos(), static fn (array $p) => $p['origin'] === 'illustration');
?>
<div class="wrap narrow page-head">
  <h1><?= e(t('credits.title')) ?></h1>
  <p class="lead"><?= e(t('credits.intro')) ?></p>
</div>
<div class="wrap narrow">
  <h2 class="group-title"><?= e(t('credits.company_title')) ?></h2>
  <div class="content-card">
    <p><?= e(t('credits.company_text')) ?><?php if ($facebook !== ''): ?> <a href="<?= e($facebook) ?>" rel="noopener" target="_blank"><?= e(t('credits.facebook')) ?></a><?php endif; ?></p>
    <div class="credits-grid">
      <?php foreach ($company as $m): ?><figure class="credit-thumb"><img src="<?= e($m['path']) ?>" alt="<?= e(\App\Core\Lang::get() === 'en' && $m['alt_en'] ? $m['alt_en'] : $m['alt']) ?>" loading="lazy" decoding="async"></figure><?php endforeach; ?>
    </div>
  </div>

  <?php if ($illustrations): ?>
  <h2 class="group-title"><?= e(t('credits.illustration_title')) ?></h2>
  <div class="content-card">
    <p><?= e(t('credits.illustration_text')) ?></p>
    <ul class="credits-list">
      <?php foreach ($illustrations as $key => $p): ?>
      <li>
        <figure class="credit-thumb"><?= photo($key, '', '160px') ?></figure>
        <div>
          <p><strong><?= e($p['alt'][\App\Core\Lang::get()] ?? $p['alt']['fr']) ?></strong></p>
          <p class="small"><?= e(t('credits.author')) ?> : <?= e($p['author']) ?> · <?= e(t('credits.license')) ?> : <a href="<?= e($p['license_url']) ?>" rel="noopener license" target="_blank"><?= e($p['license']) ?></a> · <?= e(t('credits.source')) ?> : <a href="<?= e($p['source']) ?>" rel="noopener" target="_blank">Wikimedia Commons</a></p>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
<div class="section"></div>
