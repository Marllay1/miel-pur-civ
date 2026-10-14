<?php /** @var array $products @var string $q @var string $statut */ ?>
<div class="admin-head">
  <h1>Produits</h1>
  <a class="btn btn-primary btn-small" href="/admin/produits/nouveau">Nouveau produit</a>
</div>
<form class="toolbar" method="get" action="/admin/produits">
  <label class="sr-only" for="q">Rechercher</label><input id="q" name="q" type="search" placeholder="Nom ou référence" value="<?= e($q) ?>">
  <label class="sr-only" for="statut">Statut</label>
  <select id="statut" name="statut"><option value="">Tous</option><option value="actif"<?= $statut === 'actif' ? ' selected' : '' ?>>Actifs</option><option value="inactif"<?= $statut === 'inactif' ? ' selected' : '' ?>>Masqués</option></select>
  <button class="btn btn-ghost btn-small" type="submit">Filtrer</button>
</form>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Produit</th><th>Catégorie</th><th class="num">Variantes</th><th class="num">Stock</th><th class="num">Dès</th><th>État</th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr>
      <td><a href="/admin/produits/<?= (int) $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a><?= (int) $p['is_demo'] ? ' <span class="badge badge-demo">Démo</span>' : '' ?><?= (int) $p['is_featured'] ? ' <span class="badge">À la une</span>' : '' ?></td>
      <td><?= e($p['category_name'] ?? '—') ?></td>
      <td class="num"><?= (int) $p['variant_count'] ?></td>
      <td class="num"><?= (int) $p['stock'] ?></td>
      <td class="num"><?= $p['price_from'] !== null ? money((int) $p['price_from']) : '—' ?></td>
      <td><?= (int) $p['is_active'] ? '<span class="status status-delivered">En ligne</span>' : '<span class="status status-cancelled">Masqué</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$products): ?><tr><td colspan="6" class="muted">Aucun produit.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
