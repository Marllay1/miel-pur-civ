<?php
use App\Controllers\Admin\ProductController;
/** @var array $products @var string $q @var string $statut */
$low = (int) setting('low_stock_threshold');
$statusClass = ['draft' => 'status-pending_payment', 'published' => 'status-delivered', 'disabled' => 'status-cancelled'];
?>
<div class="admin-head">
  <h1>Produits</h1>
  <a class="btn btn-primary btn-small" href="/admin/produits/nouveau">Nouveau produit</a>
</div>
<form class="toolbar" method="get" action="/admin/produits">
  <label class="sr-only" for="q">Rechercher</label><input id="q" name="q" type="search" placeholder="Nom ou référence" value="<?= e($q) ?>">
  <label class="sr-only" for="statut">Statut</label>
  <select id="statut" name="statut"><option value="">Tous</option>
    <?php foreach (ProductController::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $statut === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    <option value="a-verifier"<?= $statut === 'a-verifier' ? ' selected' : '' ?>>À vérifier (photo, référence…)</option>
  </select>
  <button class="btn btn-ghost btn-small" type="submit">Filtrer</button>
</form>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Produit</th><th>Référence</th><th>Catégorie</th><th class="num">Variantes</th><th class="num">Stock</th><th class="num">Dès</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr>
      <td><div class="order-line"><?php if ($p['image_path']): ?><img class="mini-thumb" src="<?= e(\App\Services\ImageService::small((string) $p['image_path'])) ?>" alt="" width="40" height="40" loading="lazy"><?php else: ?><span class="mini-thumb mini-thumb-empty" aria-hidden="true"></span><?php endif; ?>
        <span><a href="/admin/produits/<?= (int) $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a><?= (int) $p['is_demo'] ? ' <span class="badge badge-demo">Démo</span>' : '' ?><?= (int) $p['is_featured'] ? ' <span class="badge">À la une</span>' : '' ?>
        <?php if ($p['review_note'] || (int) $p['image_count'] === 0): ?><br><span class="small status status-pending_payment" title="<?= e((string) $p['review_note']) ?>"><?= (int) $p['image_count'] === 0 ? 'Photo à fournir' : 'À vérifier' ?></span><?php endif; ?></span></div></td>
      <td><?= $p['reference'] !== null ? '<code>' . e($p['reference']) . '</code>' : '<span class="small muted">à compléter</span>' ?></td>
      <td><?= e($p['category_name'] ?? '—') ?></td>
      <td class="num"><?= (int) $p['variant_count'] ?></td>
      <td class="num"><?= (int) $p['stock'] ?><?= (int) $p['stock'] === 0 ? '<br><span class="small status status-cancelled">Rupture</span>' : ((int) $p['stock'] <= $low ? '<br><span class="small status status-pending_payment">Stock faible</span>' : '') ?></td>
      <td class="num"><?= $p['price_from'] !== null ? money((int) $p['price_from']) : '—' ?></td>
      <td><span class="status <?= $statusClass[$p['status']] ?? '' ?>"><?= e(ProductController::STATUSES[$p['status']] ?? $p['status']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$products): ?><tr><td colspan="7" class="muted">Aucun produit.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
