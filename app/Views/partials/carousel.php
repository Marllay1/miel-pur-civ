<?php
/**
 * Carrousel de l'accueil (diapositives de la table home_slides, Administration → Carrousel de l'accueil).
 * Première image chargée en priorité, suivantes en différé ; image dédiée aux petits écrans si fournie.
 * Sans JavaScript : diapositives consultables par défilement horizontal. Avec JavaScript : fondu, commandes,
 * indicateurs, défilement automatique facultatif (mis en pause au survol, au focus, au toucher, et désactivé
 * si le visiteur demande de réduire les animations), navigation au clavier (flèches).
 * @var array $slides diapositives publiées, déjà localisées
 * @var bool $preview aperçu dans l'administration (liens neutralisés)
 */
$preview ??= false;
$count = count($slides);
$autoplay = !$preview && $count > 1 && \App\Services\Settings::bool('carousel_autoplay');
$interval = max(4, min(15, \App\Services\Settings::int('carousel_interval') ?: 7)) * 1000;
?>
<section class="carousel" data-carousel aria-roledescription="<?= e(t('carousel.role')) ?>" aria-label="<?= e(t('carousel.label')) ?>"<?= $autoplay ? ' data-autoplay="' . $interval . '"' : '' ?>>
  <div class="carousel-track" data-carousel-track>
    <?php foreach ($slides as $i => $s): $label = $i + 1 . ' / ' . $count; ?>
    <article class="carousel-slide<?= $i === 0 ? ' is-active' : '' ?>" data-slide role="group" aria-roledescription="<?= e(t('carousel.slide')) ?>" aria-label="<?= e($label) ?>"<?= $i === 0 ? '' : ' aria-hidden="true"' ?>>
      <picture class="carousel-media">
        <?php if ((string) $s['image_mobile_path'] !== ''): ?><source media="(max-width: 699px)" srcset="<?= e($s['image_mobile_path']) ?>"><?php endif; ?>
        <img src="<?= e($s['image_path']) ?>" alt="<?= e((string) $s['image_alt']) ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
      </picture>
      <div class="carousel-content">
        <div class="carousel-panel">
          <?php if ($i === 0 && !$preview): ?><h1 class="carousel-title"><?= e($s['title']) ?></h1><?php else: ?><h2 class="carousel-title"><?= e($s['title']) ?></h2><?php endif; ?>
          <?php if ((string) $s['body'] !== ''): ?><p class="carousel-text"><?= e($s['body']) ?></p><?php endif; ?>
          <?php if ((string) $s['button_label'] !== '' && (string) $s['link_url'] !== ''): ?>
          <a class="btn btn-large" href="<?= $preview ? '#' : e($s['link_url']) ?>"<?= str_starts_with((string) $s['link_url'], 'https://') ? ' rel="noopener"' : '' ?><?= $i === 0 ? '' : ' tabindex="-1"' ?>><?= e($s['button_label']) ?> <?= icon('arrow-right') ?></a>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php if ($count > 1): ?>
  <div class="carousel-controls" data-carousel-controls hidden>
    <button class="carousel-btn" type="button" data-carousel-prev aria-label="<?= e(t('carousel.prev')) ?>"><?= icon('chevron-left') ?></button>
    <div class="carousel-dots" role="group" aria-label="<?= e(t('carousel.choose')) ?>">
      <?php foreach ($slides as $i => $s): ?><button type="button" class="carousel-dot" data-carousel-dot="<?= $i ?>" aria-label="<?= e(t('carousel.go', ['n' => $i + 1, 'title' => $s['title']])) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button><?php endforeach; ?>
    </div>
    <button class="carousel-btn" type="button" data-carousel-next aria-label="<?= e(t('carousel.next')) ?>"><?= icon('chevron') ?></button>
    <?php if ($autoplay): ?><button class="carousel-btn carousel-pause" type="button" data-carousel-pause aria-label="<?= e(t('carousel.pause')) ?>" data-label-pause="<?= e(t('carousel.pause')) ?>" data-label-play="<?= e(t('carousel.play')) ?>"><svg class="cp-pause" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="3.5" y="3" width="3" height="10" rx="1"/><rect x="9.5" y="3" width="3" height="10" rx="1"/></svg><svg class="cp-play" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M5 3.2v9.6L13 8z"/></svg></button><?php endif; ?>
  </div>
  <?php endif; ?>
</section>
