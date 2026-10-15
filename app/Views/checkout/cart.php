<?php
/** @var array $summary @var array $zones */
use App\Services\Settings;
$items = $summary['items'];
$max = Settings::int('max_qty_per_line');
?>
<div class="wrap page-head"><h1><?= e(t('cart.title')) ?></h1></div>
<div class="wrap">
<?php if (!$items): ?>
  <div class="empty-state">
    <?= icon('bag') ?>
    <h2><?= e(t('cart.empty_title')) ?></h2>
    <p class="muted"><?= e(t('cart.empty_hint')) ?></p>
    <a class="btn" href="/boutique"><?= e(t('home.cta_shop')) ?></a>
  </div>
  <div class="section"></div>
<?php else: ?>
  <?php foreach ($summary['problems'] as $p): ?><p class="notice notice-amber"><?= icon('info') ?> <?= e($p) ?></p><?php endforeach; ?>
  <?php if ($summary['promo_error']): ?><p class="notice notice-amber"><?= icon('info') ?> <?= e(t('cart.promo_problem', ['code' => $summary['cart']['promo_code'], 'error' => $summary['promo_error']])) ?></p><?php endif; ?>
  <div class="cart-layout">
    <section class="group" aria-label="<?= e(t('cart.items')) ?>">
      <?php foreach ($items as $item): $name = $item['product_name'] . ' – ' . $item['variant_label']; ?>
      <div class="cart-item<?= !$item['available'] ? ' is-unavailable' : '' ?>">
        <div class="cart-item-media"><?php if ($item['image_path']): ?><img src="<?= e(\App\Services\ImageService::small((string) $item['image_path'])) ?>" alt="" width="88" height="88" loading="lazy"><?php endif; ?></div>
        <div>
          <p class="cart-item-name"><a href="/boutique/<?= e($item['product_slug']) ?>"><?= e($item['product_name']) ?></a></p>
          <p class="cart-item-sub"><?= e($item['variant_label']) ?> · <?= money((int) $item['price']) ?></p>
          <?php if (!$item['available']): ?><p class="cart-item-sub stock-out"><?= e(t('cart.no_longer')) ?></p>
          <?php elseif (!$item['enough_stock']): ?><p class="cart-item-sub stock-low"><?= e(t('cart.stock_available', ['count' => (int) $item['stock']])) ?></p><?php endif; ?>
        </div>
        <p class="cart-item-total"><?= money((int) $item['line_total']) ?></p>
        <div class="cart-item-actions">
          <form action="/panier/modifier" method="post" class="inline-field" data-autosubmit>
            <?= csrf_field() ?>
            <input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
            <div class="stepper" data-qty>
              <button type="button" data-qty-step="-1" aria-label="<?= e(t('product.qty_minus')) ?>"><?= icon('minus', 'icon icon-sm') ?></button>
              <label class="sr-only" for="qty-<?= (int) $item['variant_id'] ?>"><?= e(t('cart.qty_for', ['item' => $name])) ?></label>
              <input id="qty-<?= (int) $item['variant_id'] ?>" name="qty" type="number" min="0" max="<?= $max ?>" value="<?= (int) $item['quantity'] ?>" inputmode="numeric">
              <button type="button" data-qty-step="1" aria-label="<?= e(t('product.qty_plus')) ?>"><?= icon('plus', 'icon icon-sm') ?></button>
            </div>
            <button class="btn btn-small btn-gray" type="submit" data-autosubmit-btn><?= e(t('cart.update')) ?></button>
          </form>
          <form action="/panier/retirer" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
            <button class="link-btn danger" type="submit"><?= e(t('cart.remove')) ?><span class="sr-only"> <?= e($name) ?></span></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </section>

    <aside class="sticky-aside">
      <div class="group summary" aria-labelledby="h-summary">
        <h2 id="h-summary"><?= e(t('cart.summary')) ?></h2>
        <dl class="totals">
          <div><dt><?= e(t('totals.subtotal')) ?></dt><dd><?= money($summary['subtotal']) ?></dd></div>
          <?php if ($summary['promo']): ?>
          <div class="discount"><dt><?= e(t('totals.discount')) ?> (<?= e($summary['promo']['code']) ?>)</dt><dd>−<?= money($summary['discount']) ?></dd></div>
          <?php endif; ?>
          <div><dt><?= e(t('totals.shipping')) ?></dt><dd class="muted"><?= e($summary['free_shipping'] ? t('checkout.free') : t('cart.shipping_next')) ?></dd></div>
        </dl>
        <?php if ($summary['promo']): ?>
        <form action="/panier/code-promo/retirer" method="post" class="promo-form"><?= csrf_field() ?><button class="link-btn" type="submit"><?= e(t('cart.promo_remove', ['code' => $summary['promo']['code']])) ?></button></form>
        <?php else: ?>
        <form action="/panier/code-promo" method="post" class="promo-form">
          <?= csrf_field() ?>
          <label class="sr-only" for="promo-code"><?= e(t('cart.promo')) ?></label>
          <div class="inline-field">
            <input id="promo-code" name="code" maxlength="40" autocomplete="off" autocapitalize="characters" placeholder="<?= e(t('cart.promo')) ?>">
            <button class="btn btn-small btn-gray" type="submit"><?= e(t('cart.apply')) ?></button>
          </div>
        </form>
        <?php endif; ?>
        <?php if (!$summary['problems'] && !$summary['promo_error']): ?>
        <a class="btn btn-large btn-block" href="/commande"><?= icon('lock') ?><?= e(t('cart.checkout')) ?></a>
        <?php else: ?>
        <p class="small muted"><?= e(t('cart.fix_first')) ?></p>
        <?php endif; ?>
        <?php $cartPayments = \App\Services\PaymentMethods::acceptedLogos(); if ($cartPayments): ?>
        <div class="cart-payments">
          <p class="small muted"><?= e(t('footer.payments')) ?></p>
          <?= \App\Core\View::partial('payment-logos', ['methods' => $cartPayments]) ?>
        </div>
        <?php endif; ?>
        <?= \App\Core\View::partial('currency-note', ['amount' => (int) $summary['subtotal']]) ?>
        <p class="small muted"><?= e(t('cart.secure_note')) ?></p>
      </div>
      <?php if ($zones): ?>
      <h3 class="group-title"><?= e(t('cart.zones')) ?></h3>
      <div class="group">
        <?php foreach ($zones as $z): ?>
        <div class="cell no-icon"><span class="cell-body"><span class="cell-title"><?= e($z['name']) ?></span><br><span class="cell-sub"><?= e($z['delay_text']) ?></span></span><span class="cell-value"><?= (int) $z['fee'] === 0 ? e(t('checkout.free')) : money((int) $z['fee']) ?></span></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </aside>
  </div>
<?php endif; ?>
</div>
