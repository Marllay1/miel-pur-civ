<?php
use App\Core\View;
use App\Services\Settings;
/** @var array $product @var array $variants @var ?array $selected @var array $images @var array $reviews @var array $related @var array $reviewErrors @var array $reviewOld */
$maxQty = Settings::int('max_qty_per_line');
$low = Settings::int('low_stock_threshold');
$wa = wa_link(t('wa.product', ['product' => $product['name']]));
?>
<div class="wrap">
  <nav class="breadcrumb" aria-label="<?= e(t('a11y.breadcrumb')) ?>">
    <ol>
      <li><a href="/boutique"><?= e(t('nav.shop')) ?></a></li>
      <?php if ($product['category_slug']): ?><li><a href="/boutique?categorie=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a></li><?php endif; ?>
      <li aria-current="page"><?= e($product['name']) ?></li>
    </ol>
  </nav>
</div>

<article class="wrap product">
  <div class="product-gallery" data-gallery>
    <div class="product-main-media">
      <?php if ($images): ?><img src="<?= e($images[0]['path']) ?>" alt="<?= e(image_alt($images[0])) ?>" width="720" height="720" fetchpriority="high" data-gallery-main>
      <?php else: ?><span class="media-placeholder" aria-hidden="true"><?= e(mb_substr($product['name'], 0, 1)) ?></span><?php endif; ?>
    </div>
    <?php foreach ($images as $i => $img): $photoKey = photo_key_for_path((string) $img['path']); if ($photoKey === null) { continue; } ?>
    <p class="photo-note" data-gallery-credit="<?= (int) $i ?>"<?= $i === 0 ? '' : ' hidden' ?>><?= photo_credit($photoKey) ?></p>
    <?php endforeach; ?>
    <?php if (count($images) > 1): ?>
    <ul class="product-thumbs">
      <?php foreach ($images as $i => $img): ?>
      <li><button type="button" class="thumb" data-index="<?= (int) $i ?>" data-gallery-thumb="<?= e($img['path']) ?>" data-alt="<?= e(image_alt($img)) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><img src="<?= e($img['path']) ?>" alt="" width="96" height="96" loading="lazy"><span class="sr-only"><?= e(t('product.image_n', ['n' => $i + 1])) ?></span></button></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

  <div class="product-info">
    <?php if ($product['category_name']): ?><p class="product-card-cat"><?= e($product['category_name']) ?></p><?php endif; ?>
    <h1><?= e($product['name']) ?></h1>
    <?php if ($product['reference'] !== null): ?><p class="product-ref small muted"><?= e(t('product.reference')) ?> <span data-product-ref><?= e($product['reference']) ?></span></p><?php endif; ?>
    <?php if ($product['short_description']): ?><p class="lead"><?= rich_inline($product['short_description']) ?></p><?php endif; ?>

    <?php if ($selected): ?>
    <p class="product-price" data-price><?= money((int) $selected['price']) ?></p>
    <?php if (\App\Services\Currencies::enabled()): ?><p class="currency-note"><span data-conversion><?= e(implode(' · ', \App\Services\Currencies::conversions((int) $selected['price']))) ?></span> <span class="muted"><?= e(t('currency.indicative')) ?></span></p><?php endif; ?>
    <p class="stock-state <?= (int) $selected['stock'] === 0 ? 'stock-out' : ((int) $selected['stock'] <= $low ? 'stock-low' : 'stock-ok') ?>" data-stock-state>
      <?= e((int) $selected['stock'] === 0 ? t('product.out') : ((int) $selected['stock'] <= $low ? t('product.low', ['count' => (int) $selected['stock']]) : t('product.in_stock'))) ?>
    </p>

    <form class="add-to-cart" action="/panier/ajouter" method="post" data-add-to-cart>
      <?= csrf_field() ?>
      <input type="hidden" name="retour" value="/boutique/<?= e($product['slug']) ?>">
      <fieldset class="variant-picker">
        <legend class="field-label"><?= e(t('product.format')) ?></legend>
        <div class="segmented" role="radiogroup">
          <?php foreach ($variants as $v): $out = (int) $v['stock'] === 0; ?>
          <label>
            <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" required
              data-price="<?= e(money((int) $v['price'])) ?>" data-conversion="<?= e(implode(' · ', \App\Services\Currencies::conversions((int) $v['price']))) ?>" data-stock="<?= (int) $v['stock'] ?>" data-low="<?= $low ?>"
              data-label-out="<?= e(t('product.out')) ?>" data-label-low="<?= e(t('product.low', ['count' => '__N__'])) ?>" data-label-ok="<?= e(t('product.in_stock')) ?>"
              <?= (int) $v['id'] === (int) $selected['id'] ? 'checked' : '' ?><?= $out ? ' disabled' : '' ?>>
            <?= e($v['label']) ?><span class="variant-price"><?= money((int) $v['price']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="buy-row">
        <div class="stepper" data-qty>
          <button type="button" data-qty-step="-1" aria-label="<?= e(t('product.qty_minus')) ?>"><?= icon('minus', 'icon icon-sm') ?></button>
          <label class="sr-only" for="qty"><?= e(t('product.qty')) ?></label>
          <input id="qty" name="qty" type="number" min="1" max="<?= max(1, min($maxQty, (int) $selected['stock'])) ?>" value="1" inputmode="numeric" required data-max-line="<?= $maxQty ?>">
          <button type="button" data-qty-step="1" aria-label="<?= e(t('product.qty_plus')) ?>"><?= icon('plus', 'icon icon-sm') ?></button>
        </div>
        <button class="btn btn-large" type="submit"<?= (int) $selected['stock'] === 0 ? ' disabled' : '' ?> data-buy-btn><?= icon('bag') ?><?= e(t('product.add')) ?></button>
      </div>
      <p class="form-feedback small" data-cart-feedback role="status" aria-live="polite"></p>
    </form>
    <?php else: ?>
    <p class="notice"><?= icon('info') ?> <?= e(t('product.unavailable')) ?></p>
    <?php endif; ?>

    <div class="action-tiles product-meta">
      <?php if ($wa): ?><a class="action-tile" href="<?= e($wa) ?>" rel="noopener" target="_blank"><?= social_icon('whatsapp', 'icon') ?><?= e(t('product.ask_whatsapp')) ?></a><?php endif; ?>
      <?php $tel = \App\Services\SiteConfig::phones()[0] ?? null; if ($tel): ?><a class="action-tile" href="<?= e($tel['href']) ?>"><?= icon('phone') ?><?= e(t('product.call')) ?></a><?php endif; ?>
      <a class="action-tile" href="/commandes-professionnelles"><?= icon('building') ?><?= e(t('product.bulk')) ?></a>
    </div>

    <div class="group product-meta">
      <div class="cell"><span class="cell-icon green"><?= icon('leaf') ?></span><span class="cell-body"><span class="cell-title"><?= e(t('product.bio')) ?></span><br><span class="cell-sub"><?= e(t('product.bio_sub')) ?></span></span></div>
      <div class="cell"><span class="cell-icon"><?= icon('truck') ?></span><span class="cell-body"><span class="cell-title"><?= e(t('product.delivery')) ?></span><br><span class="cell-sub"><?= e(t('product.delivery_sub')) ?></span></span></div>
      <div class="cell"><span class="cell-icon gray"><?= icon('lock') ?></span><span class="cell-body"><span class="cell-title"><?= e(t('product.secure')) ?></span><br><span class="cell-sub"><?= e(t('product.secure_sub')) ?></span></span></div>
    </div>
  </div>
</article>

<div class="wrap narrow product-details">
  <div class="disclosure">
    <?php foreach (['description' => 'product.description', 'origin' => 'product.origin', 'usage_tips' => 'product.usage', 'storage_tips' => 'product.storage', 'extra_info' => 'product.extra'] as $field => $label): if (!$product[$field]) { continue; } ?>
    <details<?= $field === 'description' ? ' open' : '' ?>>
      <summary><?= e(t($label)) ?><?= icon('chevron') ?></summary>
      <div class="prose"><?= rich($product[$field]) ?></div>
    </details>
    <?php endforeach; ?>
  </div>

  <section class="reviews" id="avis" aria-labelledby="h-reviews">
    <h2 id="h-reviews" class="group-title"><?= e(t('review.title')) ?></h2>
    <?php if ($reviews): ?>
    <p class="muted"><?= e(t('review.summary', ['count' => count($reviews), 'avg' => number_format(array_sum(array_column($reviews, 'rating')) / count($reviews), 1, locale() === 'fr' ? ',' : '.', '')])) ?></p>
    <ul class="review-list">
      <?php foreach ($reviews as $rv): ?>
      <li class="review">
        <p class="small"><span class="stars" aria-label="<?= e(t('review.stars', ['n' => (int) $rv['rating']])) ?>"><?= str_repeat('★', (int) $rv['rating']) . str_repeat('☆', 5 - (int) $rv['rating']) ?></span>
          <strong><?= e($rv['author_name']) ?></strong> · <?= e(date_fr($rv['created_at'], false)) ?><?php if ((int) $rv['verified_purchase'] === 1): ?> · <span class="verified"><?= e(t('review.verified')) ?></span><?php endif; ?></p>
        <p><?= nl2br(e($rv['comment'])) ?></p>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="muted"><?= e(t('review.none')) ?></p>
    <?php endif; ?>

    <div class="disclosure product-meta">
      <details<?= $reviewErrors ? ' open' : '' ?>>
        <summary><?= e(t('review.write')) ?><?= icon('chevron') ?></summary>
        <div class="prose">
          <?= View::partial('errors-summary', ['errors' => $reviewErrors]) ?>
          <form action="/boutique/<?= e($product['slug']) ?>/avis" method="post" class="form-grid" novalidate>
            <?= csrf_field() ?><?= spam_fields() ?>
            <div class="field">
              <label for="f-author_name"><?= e(t('review.name')) ?></label>
              <input id="f-author_name" name="author_name" maxlength="100" required value="<?= e($reviewOld['author_name'] ?? '') ?>"<?= aria_invalid($reviewErrors, 'author_name') ?>>
              <?= field_error($reviewErrors, 'author_name') ?>
            </div>
            <div class="field">
              <label for="f-rating"><?= e(t('field.rating')) ?></label>
              <select id="f-rating" name="rating" required<?= aria_invalid($reviewErrors, 'rating') ?>>
                <option value=""><?= e(t('form.choose')) ?></option>
                <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= (string) ($reviewOld['rating'] ?? '') === (string) $i ? ' selected' : '' ?>><?= $i ?> / 5</option><?php endfor; ?>
              </select>
              <?= field_error($reviewErrors, 'rating') ?>
            </div>
            <div class="field field-full">
              <label for="f-comment"><?= e(t('field.comment')) ?></label>
              <textarea id="f-comment" name="comment" rows="4" minlength="10" maxlength="2000" required<?= aria_invalid($reviewErrors, 'comment') ?>><?= e($reviewOld['comment'] ?? '') ?></textarea>
              <?= field_error($reviewErrors, 'comment') ?>
            </div>
            <p class="field-full muted small"><?= e(t('review.policy')) ?></p>
            <div class="field-full"><button class="btn btn-tinted" type="submit"><?= e(t('review.send')) ?></button></div>
          </form>
        </div>
      </details>
    </div>
  </section>
</div>

<?php if ($related): ?>
<section class="section" aria-labelledby="h-related">
  <div class="wrap">
    <div class="section-head"><h2 id="h-related"><?= e(t('product.related')) ?></h2></div>
    <div class="product-rail">
      <?php foreach ($related as $p): ?><?= View::partial('product-card', ['product' => $p]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
