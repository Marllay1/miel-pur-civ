<?php
use App\Core\View;
use App\Services\OrderService;
/** @var array $orders @var array $f @var int $total @var int $page @var int $pages @var array $counts */
$query = array_filter($f);
?>
<div class="admin-head">
  <h1>Commandes <span class="muted">(<?= $total ?>)</span></h1>
  <a class="btn btn-ghost btn-small" href="<?= e(url('/admin/commandes/export', $query)) ?>">Exporter (CSV)</a>
</div>
<ul class="status-tabs">
  <li><a href="/admin/commandes"<?= $f['statut'] === '' ? ' aria-current="page"' : '' ?>>Toutes</a></li>
  <?php foreach (OrderService::STATUSES as $s): ?>
  <li><a href="<?= e(url('/admin/commandes', ['statut' => $s])) ?>"<?= $f['statut'] === $s ? ' aria-current="page"' : '' ?>><?= e(status_label($s)) ?> <span class="count"><?= $counts[$s] ?? 0 ?></span></a></li>
  <?php endforeach; ?>
</ul>
<form class="toolbar" method="get" action="/admin/commandes">
  <?php if ($f['statut']): ?><input type="hidden" name="statut" value="<?= e($f['statut']) ?>"><?php endif; ?>
  <label class="sr-only" for="q">Recherche</label><input id="q" name="q" type="search" placeholder="N°, nom, téléphone, e-mail" value="<?= e($f['q']) ?>">
  <label for="du">Du</label><input id="du" name="du" type="date" value="<?= e($f['du']) ?>">
  <label for="au">au</label><input id="au" name="au" type="date" value="<?= e($f['au']) ?>">
  <label class="sr-only" for="paiement">Paiement</label>
  <select id="paiement" name="paiement"><option value="">Tous paiements</option><option value="cod"<?= $f['paiement'] === 'cod' ? ' selected' : '' ?>>À la livraison</option><option value="demo"<?= $f['paiement'] === 'demo' ? ' selected' : '' ?>>Simulé</option></select>
  <button class="btn btn-ghost btn-small" type="submit">Filtrer</button>
</form>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Zone</th><th>Paiement</th><th>Statut</th><th class="num">Total</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><a href="/admin/commandes/<?= (int) $o['id'] ?>"><strong><?= e($o['number']) ?></strong></a><br><span class="small muted"><?= (int) $o['item_count'] ?> article(s)</span></td>
      <td class="small"><?= e(date_fr($o['created_at'])) ?></td>
      <td><?= e($o['customer_first_name'] . ' ' . $o['customer_last_name']) ?><br><span class="small"><?= e($o['customer_phone']) ?></span></td>
      <td class="small"><?= e($o['shipping_zone_name']) ?></td>
      <td class="small"><?= e(payment_method_label($o['payment_method'])) ?><br><span class="muted"><?= e(payment_status_label($o['payment_status'])) ?></span></td>
      <td><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
      <td class="num"><?= money((int) $o['total']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="7" class="muted">Aucune commande.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?= View::partial('pagination', ['page' => $page, 'pages' => $pages, 'base' => '/admin/commandes', 'query' => $query]) ?>
