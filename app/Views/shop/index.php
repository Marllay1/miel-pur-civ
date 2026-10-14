<?php
use App\Core\View;
use App\Services\Catalog;
/** @var array $result @var array $filters @var array $query @var array $categories @var ?array $currentCategory @var array $formats */
?>
<div class="page-head">
  <div class="wrap">
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
      <ol>
        <li><a href="/">Accueil</a></li>
        <?php if ($currentCategory): ?>
        <li><a href="/boutique">Boutique</a></li>
        <li aria-current="page"><?= e($currentCategory['name']) ?></li>
        <?php else: ?>
        <li aria-current="page">Boutique</li>
        <?php endif; ?>
      </ol>
    </nav>
    <h1><?= e($currentCategory['name'] ?? 'Boutique') ?></h1>
    <?php if ($currentCategory && $currentCategory['description']): ?><p class="lead"><?= rich_inline($currentCategory['description']) ?></p><?php endif; ?>
  </div>
</div>

<div class="wrap shop-layout">
  <aside class="filters" aria-labelledby="h-filters">
    <details class="filters-box" open data-filters>
      <summary id="h-filters">Filtrer et trier</summary>
      <form action="/boutique" method="get" class="filters-form">
        <div class="field">
          <label for="f-q">Recherche</label>
          <input id="f-q" name="q" type="search" value="<?= e($filters['q']) ?>" maxlength="80">
        </div>
        <div class="field">
          <label for="f-cat">Catégorie</label>
          <select id="f-cat" name="categorie">
            <option value="">Toutes</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['slug']) ?>"<?= $filters['categorie'] === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?> (<?= (int) $c['product_count'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($formats): ?>
        <div class="field">
          <label for="f-format">Format</label>
          <select id="f-format" name="format">
            <option value="">Tous</option>
            <?php foreach ($formats as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $filters['format'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <fieldset class="field price-range">
          <legend>Prix (FCFA)</legend>
          <div class="inline-field">
            <label class="sr-only" for="f-min">Prix minimum</label>
            <input id="f-min" name="prix_min" type="number" min="0" step="500" inputmode="numeric" placeholder="Min" value="<?= e($filters['prix_min']) ?>">
            <span aria-hidden="true">–</span>
            <label class="sr-only" for="f-max">Prix maximum</label>
            <input id="f-max" name="prix_max" type="number" min="0" step="500" inputmode="numeric" placeholder="Max" value="<?= e($filters['prix_max']) ?>">
          </div>
        </fieldset>
        <label class="check"><input type="checkbox" name="en_stock" value="1"<?= $filters['en_stock'] ? ' checked' : '' ?>> En stock uniquement</label>
        <div class="field">
          <label for="f-tri">Trier par</label>
          <select id="f-tri" name="tri">
            <?php foreach (Catalog::SORTS as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $filters['tri'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filters-actions">
          <button class="btn btn-primary btn-small" type="submit">Appliquer</button>
          <a class="link-btn" href="/boutique">Réinitialiser</a>
        </div>
      </form>
    </details>
  </aside>

  <section class="shop-results" aria-labelledby="h-results">
    <h2 id="h-results" class="results-count"><?= (int) $result['total'] ?> produit<?= $result['total'] > 1 ? 's' : '' ?></h2>
    <?php if ($result['items']): ?>
    <div class="product-grid">
      <?php foreach ($result['items'] as $product): ?><?= View::partial('product-card', ['product' => $product]) ?><?php endforeach; ?>
    </div>
    <?= View::partial('pagination', ['page' => $result['page'], 'pages' => $result['pages'], 'base' => '/boutique', 'query' => $query]) ?>
    <?php else: ?>
    <div class="empty-state">
      <span class="hex-outline" aria-hidden="true"></span>
      <p>Aucun produit ne correspond à votre recherche.</p>
      <a class="btn btn-ghost" href="/boutique">Voir tout le catalogue</a>
    </div>
    <?php endif; ?>
  </section>
</div>
