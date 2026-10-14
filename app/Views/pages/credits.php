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

  <?php
  // Photos libres de droits des fiches produits (provenance « Photo libre de droits · licence · auteur · source ») : crédit exigé par les licences.
  $free = \App\Core\DB::all("SELECT i.path, i.alt, i.source_note FROM product_images i JOIN products p ON p.id = i.product_id
      WHERE p.is_active = 1 AND p.deleted_at IS NULL AND i.source_note LIKE 'Photo libre de droits%' ORDER BY p.name, i.sort_order, i.id");
  ?>
  <?php if ($free): ?>
  <h2 class="group-title"><?= e(t('credits.free_title')) ?></h2>
  <div class="content-card">
    <p><?= e(t('credits.free_text')) ?></p>
    <ul class="credits-list">
      <?php foreach ($free as $f): $parts = array_map('trim', explode('·', (string) $f['source_note'])); $url = (string) end($parts); ?>
      <li>
        <figure class="credit-thumb"><img src="<?= e($f['path']) ?>" alt="" loading="lazy" decoding="async"></figure>
        <div>
          <p><strong><?= e(image_alt($f)) ?></strong></p>
          <p class="small"><?= e(t('credits.author')) ?> : <?= e($parts[2] ?? '—') ?> · <?= e(t('credits.license')) ?> : <?= e($parts[1] ?? '—') ?><?php if (str_starts_with($url, 'https://')): ?> · <?= e(t('credits.source')) ?> : <a href="<?= e($url) ?>" rel="noopener" target="_blank">Wikimedia Commons</a><?php endif; ?></p>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

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
