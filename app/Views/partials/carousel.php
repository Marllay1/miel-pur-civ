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
        <img src="<?= e($s['image_path']) ?>"<?= img_srcset((string) $s['image_path'], '100vw') ?> alt="<?= e((string) $s['image_alt']) ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
      </picture>
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
  </div>
  <?php endif; ?>
</section>
