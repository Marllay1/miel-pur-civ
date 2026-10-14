<?php
/** @var array $summary @var array $zones */
use App\Services\Settings;
$items = $summary['items'];
$max = Settings::int('max_qty_per_line');
?>
<div class="page-head">
  <div class="wrap"><h1>Votre panier</h1></div>
</div>
<div class="wrap">
<?php if (!$items): ?>
  <div class="empty-state">
    <span class="hex-outline" aria-hidden="true"></span>
    <p>Votre panier est vide.</p>
    <a class="btn btn-primary" href="/boutique">Découvrir nos produits</a>
  </div>
<?php else: ?>
  <?php foreach ($summary['problems'] as $p): ?><p class="flash flash-error"><?= e($p) ?></p><?php endforeach; ?>
  <?php if ($summary['promo_error']): ?><p class="flash flash-error">Code promo « <?= e($summary['cart']['promo_code']) ?> » : <?= e($summary['promo_error']) ?></p><?php endif; ?>
  <div class="cart-layout">
    <ul class="cart-items" aria-label="Articles du panier">
      <?php foreach ($items as $item): ?>
      <li class="cart-item<?= !$item['available'] ? ' is-unavailable' : '' ?>">
        <div class="cart-item-media"><?php if ($item['image_path']): ?><img src="<?= e($item['image_path']) ?>" alt="" width="88" height="88" loading="lazy"><?php endif; ?></div>
        <div class="cart-item-info">
          <p class="cart-item-name"><a href="/boutique/<?= e($item['product_slug']) ?>"><?= e($item['product_name']) ?></a></p>
          <p class="muted small"><?= e($item['variant_label']) ?> · <?= money((int) $item['price']) ?> l'unité</p>
          <?php if (!$item['available']): ?><p class="stock-out small">Plus disponible</p>
          <?php elseif (!$item['enough_stock']): ?><p class="stock-low small">Stock disponible : <?= (int) $item['stock'] ?></p><?php endif; ?>
        </div>
        <form class="cart-item-qty" action="/panier/modifier" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
          <label class="sr-only" for="qty-<?= (int) $item['variant_id'] ?>">Quantité pour <?= e($item['product_name'] . ' ' . $item['variant_label']) ?></label>
          <input id="qty-<?= (int) $item['variant_id'] ?>" name="qty" type="number" min="0" max="<?= $max ?>" value="<?= (int) $item['quantity'] ?>" inputmode="numeric" class="qty-input">
          <button class="btn btn-small btn-ghost" type="submit">Mettre à jour</button>
        </form>
        <p class="cart-item-total"><?= money((int) $item['line_total']) ?></p>
        <form class="cart-item-remove" action="/panier/retirer" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
          <button class="link-btn" type="submit">Retirer<span class="sr-only"> <?= e($item['product_name'] . ' ' . $item['variant_label']) ?></span></button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>

    <aside class="cart-summary" aria-labelledby="h-summary">
      <h2 id="h-summary">Récapitulatif</h2>
      <dl class="totals">
        <div><dt>Sous-total</dt><dd><?= money($summary['subtotal']) ?></dd></div>
        <?php if ($summary['promo']): ?>
        <div class="discount"><dt>Remise (<?= e($summary['promo']['code']) ?>)</dt><dd>−<?= money($summary['discount']) ?></dd></div>
        <?php if ($summary['free_shipping']): ?><div><dt>Livraison</dt><dd>Offerte</dd></div><?php endif; ?>
        <?php endif; ?>
        <?php if (!$summary['free_shipping']): ?><div><dt>Livraison</dt><dd class="muted">Calculée à l'étape suivante</dd></div><?php endif; ?>
      </dl>
      <?php if ($summary['promo']): ?>
      <form action="/panier/code-promo/retirer" method="post" class="promo-applied"><?= csrf_field() ?><button class="link-btn" type="submit">Retirer le code <?= e($summary['promo']['code']) ?></button></form>
      <?php else: ?>
      <form action="/panier/code-promo" method="post" class="promo-form">
        <?= csrf_field() ?>
        <label for="promo-code">Code promo</label>
        <div class="inline-field">
          <input id="promo-code" name="code" maxlength="40" autocomplete="off" autocapitalize="characters">
          <button class="btn btn-small btn-ghost" type="submit">Appliquer</button>
        </div>
      </form>
      <?php endif; ?>
      <?php if (!$summary['problems'] && !$summary['promo_error']): ?>
      <a class="btn btn-primary btn-block" href="/commande">Passer commande</a>
      <?php else: ?>
      <p class="small">Corrigez le panier pour continuer.</p>
      <?php endif; ?>
      <a class="link-arrow" href="/boutique">Continuer mes achats</a>
      <?php if ($zones): ?>
      <details class="zones-info">
        <summary>Zones et frais de livraison</summary>
        <ul>
          <?php foreach ($zones as $z): ?>
          <li><strong><?= e($z['name']) ?></strong> : <?= (int) $z['fee'] === 0 ? 'gratuit' : money((int) $z['fee']) ?><?= $z['free_threshold'] ? ' (offerte dès ' . money((int) $z['free_threshold']) . ')' : '' ?><?= $z['delay_text'] ? ' · ' . e($z['delay_text']) : '' ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endif; ?>
    </aside>
  </div>
<?php endif; ?>
</div>
