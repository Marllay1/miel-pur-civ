<?php
/** @var array $product */
$inStock = (int) $product['total_stock'] > 0;
$multi = (int) $product['variant_count'] > 1;
?>
<article class="product-card">
  <a class="product-card-link" href="/boutique/<?= e($product['slug']) ?>">
    <div class="product-card-media">
      <?php if (!empty($product['image_path'])): ?>
      <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['image_alt'] ?? $product['name']) ?>" loading="lazy" width="480" height="480">
      <?php endif; ?>
      <?php if (!$inStock): ?><span class="badge badge-out">Rupture</span><?php endif; ?>
      <?php if (!empty($product['is_demo'])): ?><span class="badge badge-demo">Démo</span><?php endif; ?>
    </div>
    <div class="product-card-body">
      <?php if (!empty($product['category_name'])): ?><p class="product-card-cat"><?= e($product['category_name']) ?></p><?php endif; ?>
      <h3 class="product-card-title"><?= e($product['name']) ?></h3>
      <p class="product-card-price">
        <?php if ($multi): ?><span class="from">à partir de</span> <?php endif; ?><?= money((int) $product['price_from']) ?>
      </p>
    </div>
  </a>
</article>
