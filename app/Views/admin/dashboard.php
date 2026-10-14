<?php
/** @var ?array $kpi @var array $recent @var array $lowStock @var array $todo */
?>
<h1>Tableau de bord</h1>
<p class="muted small">Chiffres calculés en temps réel à partir des commandes enregistrées. Aucune donnée estimée.</p>

<?php if ($kpi): ?>
<div class="kpi-grid">
  <div class="kpi"><span class="kpi-value"><?= $kpi['orders_today'] ?></span><span class="kpi-label">commande(s) aujourd'hui</span></div>
  <div class="kpi"><span class="kpi-value"><?= $kpi['orders_7d'] ?></span><span class="kpi-label">sur 7 jours</span></div>
  <div class="kpi"><span class="kpi-value"><?= $kpi['orders_30d'] ?></span><span class="kpi-label">non annulées sur 30 jours</span></div>
  <div class="kpi kpi-amber"><span class="kpi-value"><?= money($kpi['paid_30d']) ?></span><span class="kpi-label">encaissé sur 30 jours</span></div>
  <div class="kpi"><span class="kpi-value"><?= money($kpi['pending_value']) ?></span><span class="kpi-label">à encaisser (commandes en cours)</span></div>
  <div class="kpi"><span class="kpi-value"><?= $kpi['customers'] ?></span><span class="kpi-label">compte(s) client</span></div>
</div>
<div class="action-row">
  <a class="action-card" href="/admin/commandes?statut=pending_confirmation"><strong><?= $kpi['to_confirm'] ?></strong> à confirmer par téléphone</a>
  <a class="action-card" href="/admin/commandes?statut=preparing"><strong><?= $kpi['to_prepare'] ?></strong> payée(s) ou en préparation</a>
  <a class="action-card" href="/admin/commandes?statut=pending_payment"><strong><?= $kpi['awaiting_payment'] ?></strong> en attente de paiement</a>
</div>
<?php endif; ?>

<div class="admin-cols">
  <?php if ($kpi !== null): ?>
  <section class="card">
    <h2>Dernières commandes</h2>
    <?php if (!$recent): ?><p class="muted">Aucune commande pour l'instant.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>N°</th><th>Client</th><th>Statut</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr><td><a href="/admin/commandes/<?= (int) $o['id'] ?>"><?= e($o['number']) ?></a><br><span class="muted small"><?= e(date_fr($o['created_at'])) ?></span></td>
        <td><?= e($o['customer_first_name'] . ' ' . $o['customer_last_name']) ?></td>
        <td><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
        <td class="num"><?= money((int) $o['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <div>
    <?php if ($todo): ?>
    <section class="card">
      <h2>À traiter</h2>
      <ul class="todo-list">
        <?php foreach ($todo as $label => [$n, $href]): ?>
        <li><a href="<?= e($href) ?>"><?= e($label) ?></a> <span class="count<?= $n > 0 ? ' count-hot' : '' ?>"><?= (int) $n ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
    <?php if ($lowStock): ?>
    <section class="card">
      <h2>Stock faible</h2>
      <ul class="todo-list">
        <?php foreach ($lowStock as $v): ?>
        <li><?php if (\App\Core\Auth::can('catalog.manage')): ?><a href="/admin/produits/<?= (int) $v['product_id'] ?>"><?= e($v['product_name'] . ' – ' . $v['label']) ?></a><?php else: ?><span><?= e($v['product_name'] . ' – ' . $v['label']) ?></span><?php endif; ?> <span class="count<?= (int) $v['stock'] === 0 ? ' count-hot' : '' ?>"><?= (int) $v['stock'] ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if (\App\Core\Auth::can('catalog.manage')): ?><p><a href="/admin/stock?faible=1">Gérer le stock</a></p><?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</div>
