<?php /** @var array $variants @var array $movements @var int $threshold @var bool $onlyLow */
$reasons = ['order' => 'Vente', 'cancel' => 'Annulation', 'adjust' => 'Ajustement', 'initial' => 'Stock initial'];
?>
<div class="admin-head">
  <h1>Stock</h1>
  <p><?= $onlyLow ? '<a href="/admin/stock">Tout afficher</a>' : '<a href="/admin/stock?faible=1">Stock faible uniquement (≤ ' . $threshold . ')</a>' ?></p>
</div>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Produit / variante</th><th>Réf.</th><th class="num">Stock</th><th>Nouvelle quantité et motif</th></tr></thead>
  <tbody>
  <?php foreach ($variants as $v): ?>
    <tr class="<?= (int) $v['stock'] <= $threshold ? 'row-warn' : '' ?>">
      <td><a href="/admin/produits/<?= (int) $v['product_id'] ?>"><?= e($v['product_name']) ?></a> – <?= e($v['label']) ?><?= (int) $v['is_active'] ? '' : ' <span class="muted">(inactive)</span>' ?></td>
      <td><code><?= e($v['sku']) ?></code></td>
      <td class="num"><strong><?= (int) $v['stock'] ?></strong></td>
      <td>
        <form action="/admin/stock/<?= (int) $v['id'] ?>" method="post" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="current" value="<?= (int) $v['stock'] ?>">
          <label class="sr-only" for="s-<?= (int) $v['id'] ?>">Nouvelle quantité</label>
          <input id="s-<?= (int) $v['id'] ?>" name="stock" type="number" min="0" required value="<?= (int) $v['stock'] ?>" class="input-num">
          <label class="sr-only" for="n-<?= (int) $v['id'] ?>">Motif</label>
          <input id="n-<?= (int) $v['id'] ?>" name="note" maxlength="255" required placeholder="Motif (inventaire, réception…)">
          <button class="btn btn-ghost btn-small" type="submit">Ajuster</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>Derniers mouvements</h2>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Date</th><th>Article</th><th>Motif</th><th class="num">Quantité</th><th>Par</th><th>Note</th></tr></thead>
  <tbody>
  <?php foreach ($movements as $m): ?>
    <tr><td><?= e(date_fr($m['created_at'])) ?></td><td><?= e($m['product_name'] . ' – ' . $m['label']) ?></td><td><?= e($reasons[$m['reason']] ?? $m['reason']) ?><?= $m['reference'] ? ' · ' . e($m['reference']) : '' ?></td>
    <td class="num <?= (int) $m['quantity'] < 0 ? 'neg' : 'pos' ?>"><?= (int) $m['quantity'] > 0 ? '+' : '' ?><?= (int) $m['quantity'] ?></td>
    <td><?= $m['first_name'] ? e($m['first_name'] . ' ' . $m['last_name']) : '<span class="muted">système</span>' ?></td><td><?= e($m['note']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
