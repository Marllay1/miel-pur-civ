<?php
/** @var array $product */
$inStock = (int) $product['total_stock'] > 0;
$multi = (int) $product['variant_count'] > 1;
?>
<article class="product-card">
  <a class="product-card-link" href="/boutique/<?= e($product['slug']) ?>">
    <div class="product-card-media">
      <?php if (!empty($product['image_path'])): $photoKey = photo_key_for_path((string) $product['image_path']); ?>
      <img src="<?= e(\App\Services\ImageService::small((string) $product['image_path'])) ?>"<?= img_srcset((string) $product['image_path'], '(min-width: 1100px) 25vw, (min-width: 700px) 33vw, 50vw') ?> alt="" loading="lazy" decoding="async" width="480" height="480">
      <?php if ($photoKey !== null && photos()[$photoKey]['origin'] === 'illustration'): ?><span class="media-badge"><?= e(t('photo.illustration_short')) ?></span><?php endif; ?>
      <?php else: ?>
      <span class="media-placeholder" aria-hidden="true"><?= e(mb_substr($product['name'], 0, 1)) ?></span>
      <?php endif; ?>
      <?php if (!$inStock): ?><span class="pill pill-out"><?= e(t('product.out')) ?></span>
      <?php elseif (!empty($product['is_featured'])): ?><span class="pill"><?= icon('star', 'icon icon-sm') ?><?= e(t('product.favourite')) ?></span><?php endif; ?>
    </div>
    <div class="product-card-body">
      <?php if (!empty($product['category_name'])): ?><p class="product-card-cat"><?= e($product['category_name']) ?></p><?php endif; ?>
      <h3 class="product-card-title"><?= e($product['name']) ?></h3>
      <p class="product-card-price"><?php if ($multi): ?><span class="from"><?= e(t('product.from')) ?></span><?php endif; ?><?= money((int) $product['price_from']) ?></p>
    </div>
  </a>
</article>
