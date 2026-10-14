<?php
use App\Core\View;
use App\Services\Catalog;
/** @var array $result @var array $filters @var array $query @var array $categories @var ?array $currentCategory @var array $formats */
$activeFilters = array_filter([$filters['format'], $filters['prix_min'], $filters['prix_max'], $filters['en_stock'] ? '1' : null, $filters['tri'] !== 'pertinence' ? $filters['tri'] : null], static fn ($v) => $v !== null && $v !== '');
?>
<div class="wrap page-head">
  <h1><?= e($currentCategory['name'] ?? t('nav.shop')) ?></h1>
  <?php if ($currentCategory && $currentCategory['description']): ?><p class="lead"><?= e($currentCategory['description']) ?></p><?php else: ?><p class="lead"><?= e(t('shop.lead')) ?></p><?php endif; ?>
</div>

<div class="wrap">
  <form action="/boutique" method="get" class="searchbar" role="search" id="recherche">
    <?= icon('search') ?>
    <label class="sr-only" for="f-q"><?= e(t('shop.search')) ?></label>
    <input id="f-q" name="q" type="search" value="<?= e($filters['q']) ?>" maxlength="80" placeholder="<?= e(t('shop.search_placeholder')) ?>" data-search-input>
    <?php if ($filters['categorie'] !== ''): ?><input type="hidden" name="categorie" value="<?= e($filters['categorie']) ?>"><?php endif; ?>
  </form>

  <nav class="chips" aria-label="<?= e(t('shop.categories')) ?>">
    <a class="chip" href="<?= e(url('/boutique', array_diff_key($query, ['categorie' => 1, 'page' => 1]))) ?>"<?= $filters['categorie'] === '' ? ' aria-current="page"' : '' ?>><?= e(t('shop.all')) ?></a>
    <?php foreach ($categories as $c): ?>
    <a class="chip" href="<?= e(url('/boutique', ['categorie' => $c['slug']] + array_diff_key($query, ['page' => 1]))) ?>"<?= $filters['categorie'] === $c['slug'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?> <span class="count"><?= (int) $c['product_count'] ?></span></a>
    <?php endforeach; ?>
  </nav>

  <div class="shop-toolbar">
    <p class="results-count" aria-live="polite"><?= e(t('shop.results', ['count' => (int) $result['total']])) ?></p>
  </div>

  <details class="filters-sheet"<?= $activeFilters ? ' open' : '' ?>>
    <summary class="btn btn-gray btn-small"><?= icon('filter') ?><?= e(t('shop.filters')) ?><?= $activeFilters ? ' · ' . count($activeFilters) : '' ?></summary>
    <form action="/boutique" method="get" class="filters-panel">
      <?php if ($filters['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif; ?>
      <?php if ($filters['categorie'] !== ''): ?><input type="hidden" name="categorie" value="<?= e($filters['categorie']) ?>"><?php endif; ?>
      <?php if ($formats): ?>
      <div class="field">
        <label for="f-format"><?= e(t('shop.format')) ?></label>
        <select id="f-format" name="format">
          <option value=""><?= e(t('shop.all_formats')) ?></option>
          <?php foreach ($formats as $k => $label): ?><option value="<?= e($k) ?>"<?= $filters['format'] === $k ? ' selected' : '' ?>><?= e(t('format.' . $k)) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="field">
        <label for="f-min"><?= e(t('shop.price_min')) ?></label>
        <input id="f-min" name="prix_min" type="number" min="0" step="500" inputmode="numeric" value="<?= e($filters['prix_min']) ?>">
      </div>
      <div class="field">
        <label for="f-max"><?= e(t('shop.price_max')) ?></label>
        <input id="f-max" name="prix_max" type="number" min="0" step="500" inputmode="numeric" value="<?= e($filters['prix_max']) ?>">
      </div>
      <div class="field">
        <label for="f-tri"><?= e(t('shop.sort')) ?></label>
        <select id="f-tri" name="tri">
          <?php foreach (array_keys(Catalog::SORTS) as $k): ?><option value="<?= e($k) ?>"<?= $filters['tri'] === $k ? ' selected' : '' ?>><?= e(t('sort.' . $k)) ?></option><?php endforeach; ?>
        </select>
      </div>
      <label class="switch field-full"><span><?= e(t('shop.in_stock')) ?></span><input type="checkbox" name="en_stock" value="1"<?= $filters['en_stock'] ? ' checked' : '' ?>></label>
      <div class="filters-actions">
        <button class="btn btn-small" type="submit"><?= e(t('shop.apply')) ?></button>
        <a class="btn btn-small btn-plain" href="<?= e(url('/boutique', ['categorie' => $filters['categorie'] ?: null])) ?>"><?= e(t('shop.reset')) ?></a>
      </div>
    </form>
  </details>

  <?php if ($result['items']): ?>
  <div class="product-grid">
    <?php foreach ($result['items'] as $product): ?><?= View::partial('product-card', ['product' => $product]) ?><?php endforeach; ?>
  </div>
  <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'base' => '/boutique', 'query' => $query]) ?>
  <?php else: ?>
  <div class="empty-state">
    <?= icon('search') ?>
    <h2><?= e(t('shop.empty')) ?></h2>
    <p class="muted"><?= e(t('shop.empty_hint')) ?></p>
    <a class="btn btn-tinted" href="/boutique"><?= e(t('shop.see_catalog')) ?></a>
  </div>
  <?php endif; ?>
</div>
<div class="section"></div>
