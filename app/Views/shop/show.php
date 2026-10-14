<?php
use App\Core\View;
use App\Services\Catalog;
use App\Services\Settings;
/** @var array $product @var array $variants @var ?array $selected @var array $images @var array $reviews @var array $related */
$maxQty = Settings::int('max_qty_per_line');
$low = Settings::int('low_stock_threshold');
?>
<div class="wrap">
  <nav class="breadcrumb" aria-label="Fil d'Ariane">
    <ol>
      <li><a href="/">Accueil</a></li>
      <li><a href="/boutique">Boutique</a></li>
      <?php if ($product['category_slug']): ?><li><a href="/boutique?categorie=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a></li><?php endif; ?>
      <li aria-current="page"><?= e($product['name']) ?></li>
    </ol>
  </nav>
</div>

<article class="wrap product">
  <div class="product-gallery" data-gallery>
    <div class="product-main-media">
      <?php if ($images): ?>
      <img src="<?= e($images[0]['path']) ?>" alt="<?= e($images[0]['alt']) ?>" width="720" height="720" data-gallery-main>
      <?php endif; ?>
      <?php if ((int) $product['is_demo'] === 1): ?><span class="badge badge-demo">Produit et illustration de démonstration</span><?php endif; ?>
    </div>
    <?php if (count($images) > 1): ?>
    <ul class="product-thumbs">
      <?php foreach ($images as $i => $img): ?>
      <li><button type="button" class="thumb" data-gallery-thumb="<?= e($img['path']) ?>" data-alt="<?= e($img['alt']) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><img src="<?= e($img['path']) ?>" alt="" width="96" height="96" loading="lazy"><span class="sr-only">Voir l'image <?= $i + 1 ?></span></button></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

  <div class="product-info">
    <?php if ($product['category_name']): ?><p class="eyebrow"><?= e($product['category_name']) ?></p><?php endif; ?>
    <h1><?= e($product['name']) ?></h1>
    <?php if ($product['short_description']): ?><p class="lead"><?= rich_inline($product['short_description']) ?></p><?php endif; ?>

    <?php if ($selected): ?>
    <p class="product-price" data-price><?= money((int) $selected['price']) ?></p>
    <p class="stock-state" data-stock-state>
      <?php if ((int) $selected['stock'] === 0): ?><span class="stock-out">Rupture de stock</span>
      <?php elseif ((int) $selected['stock'] <= $low): ?><span class="stock-low">Plus que <?= (int) $selected['stock'] ?> en stock</span>
      <?php else: ?><span class="stock-ok">En stock</span><?php endif; ?>
    </p>

    <form class="add-to-cart" action="/panier/ajouter" method="post" data-add-to-cart>
      <?= csrf_field() ?>
      <input type="hidden" name="retour" value="/boutique/<?= e($product['slug']) ?>">
      <fieldset class="variant-picker">
        <legend>Format</legend>
        <div class="variant-options">
          <?php foreach ($variants as $v): $out = (int) $v['stock'] === 0; ?>
          <label class="variant-option<?= $out ? ' is-out' : '' ?>">
            <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" required
              data-price="<?= e(money((int) $v['price'])) ?>" data-stock="<?= (int) $v['stock'] ?>" data-low="<?= $low ?>"
              <?= (int) $v['id'] === (int) $selected['id'] ? 'checked' : '' ?><?= $out ? ' disabled' : '' ?>>
            <span class="variant-label"><?= e($v['label']) ?></span>
            <span class="variant-meta"><?= e(Catalog::FORMATS[$v['format']] ?? $v['format']) ?> · <?= money((int) $v['price']) ?><?= $out ? ' · épuisé' : '' ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="buy-row">
        <div class="qty-field">
          <label for="qty">Quantité</label>
          <div class="qty-stepper" data-qty>
            <button type="button" class="qty-btn" data-qty-step="-1" aria-label="Diminuer la quantité">−</button>
            <input id="qty" name="qty" type="number" min="1" max="<?= max(1, min($maxQty, (int) $selected['stock'])) ?>" value="1" inputmode="numeric" required data-max-line="<?= $maxQty ?>">
            <button type="button" class="qty-btn" data-qty-step="1" aria-label="Augmenter la quantité">+</button>
          </div>
        </div>
        <button class="btn btn-primary btn-buy" type="submit"<?= (int) $selected['stock'] === 0 ? ' disabled' : '' ?> data-buy-btn>Ajouter au panier</button>
      </div>
      <p class="form-feedback" data-cart-feedback role="status" aria-live="polite"></p>
    </form>
    <?php else: ?>
    <p class="stock-out">Ce produit n'est pas disponible actuellement.</p>
    <?php endif; ?>

    <ul class="hex-list product-assurances">
      <?php if (Settings::bool('cod_enabled')): ?><li>Paiement à la livraison possible selon la zone</li><?php endif; ?>
      <li>Frais de livraison calculés à l'étape « Livraison »</li>
      <li>Gros volumes : <a href="/commandes-professionnelles">demandez un devis</a></li>
    </ul>
  </div>

  <div class="product-details">
    <?php if ($product['description']): ?>
    <section aria-labelledby="h-desc"><h2 id="h-desc">Description</h2><div class="prose"><?= rich($product['description']) ?></div></section>
    <?php endif; ?>
    <?php if ($product['origin']): ?>
    <section aria-labelledby="h-origin"><h2 id="h-origin">Origine</h2><div class="prose"><?= rich($product['origin']) ?></div></section>
    <?php endif; ?>
    <?php if ($product['usage_tips']): ?>
    <section aria-labelledby="h-usage"><h2 id="h-usage">Utilisations</h2><div class="prose"><?= rich($product['usage_tips']) ?></div></section>
    <?php endif; ?>
    <?php if ($product['storage_tips']): ?>
    <section aria-labelledby="h-storage"><h2 id="h-storage">Conservation</h2><div class="prose"><?= rich($product['storage_tips']) ?></div></section>
    <?php endif; ?>
  </div>
</article>

<section class="wrap reviews" id="avis" aria-labelledby="h-reviews">
  <h2 id="h-reviews">Avis clients</h2>
  <?php if ($reviews): ?>
  <p class="reviews-summary"><?= count($reviews) ?> avis vérifié<?= count($reviews) > 1 ? 's' : '' ?> par notre équipe · note moyenne <?= e(number_format(array_sum(array_column($reviews, 'rating')) / count($reviews), 1, ',', '')) ?> / 5</p>
  <ul class="review-list">
    <?php foreach ($reviews as $rv): ?>
    <li class="review">
      <p class="review-head"><span class="stars" aria-label="Note : <?= (int) $rv['rating'] ?> sur 5"><?= str_repeat('★', (int) $rv['rating']) . str_repeat('☆', 5 - (int) $rv['rating']) ?></span>
        <strong><?= e($rv['author_name']) ?></strong> · <?= e(date_fr($rv['created_at'], false)) ?><?php if ((int) $rv['verified_purchase'] === 1): ?> · <span class="verified">Achat vérifié</span><?php endif; ?></p>
      <p><?= nl2br(e($rv['comment'])) ?></p>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="muted">Aucun avis publié pour le moment.</p>
  <?php endif; ?>

  <details class="review-form-box"<?= $reviewErrors ? ' open' : '' ?>>
    <summary>Donner votre avis</summary>
    <?= View::partial('errors-summary', ['errors' => $reviewErrors]) ?>
    <form action="/boutique/<?= e($product['slug']) ?>/avis" method="post" class="form-grid" novalidate>
      <?= csrf_field() ?><?= spam_fields() ?>
      <div class="field">
        <label for="f-author_name">Votre prénom ou pseudonyme</label>
        <input id="f-author_name" name="author_name" maxlength="100" required value="<?= e($reviewOld['author_name'] ?? '') ?>"<?= aria_invalid($reviewErrors, 'author_name') ?>>
        <?= field_error($reviewErrors, 'author_name') ?>
      </div>
      <div class="field">
        <label for="f-rating">Note</label>
        <select id="f-rating" name="rating" required<?= aria_invalid($reviewErrors, 'rating') ?>>
          <option value="">Choisir…</option>
          <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= (string) ($reviewOld['rating'] ?? '') === (string) $i ? ' selected' : '' ?>><?= $i ?> / 5</option><?php endfor; ?>
        </select>
        <?= field_error($reviewErrors, 'rating') ?>
      </div>
      <div class="field field-full">
        <label for="f-comment">Votre avis</label>
        <textarea id="f-comment" name="comment" rows="4" minlength="10" maxlength="2000" required<?= aria_invalid($reviewErrors, 'comment') ?>><?= e($reviewOld['comment'] ?? '') ?></textarea>
        <?= field_error($reviewErrors, 'comment') ?>
      </div>
      <p class="field-full muted small">Les avis sont publiés après vérification. Ils ne sont ni rémunérés ni modifiés.</p>
      <div class="field-full"><button class="btn btn-ghost" type="submit">Envoyer mon avis</button></div>
    </form>
  </details>
</section>

<?php if ($related): ?>
<section class="section" aria-labelledby="h-related">
  <div class="wrap">
    <h2 id="h-related" class="section-title">Vous aimerez aussi</h2>
    <div class="product-grid">
      <?php foreach ($related as $p): ?><?= View::partial('product-card', ['product' => $p]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
