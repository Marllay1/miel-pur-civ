<?php /** @var array $rows @var array $f @var array $methods @var array $totals */ ?>
<div class="admin-head">
  <h1>Journal des paiements</h1>
  <a class="btn btn-ghost btn-small" href="<?= e(url('/admin/paiements/journal/export', array_filter($f))) ?>">Exporter (CSV)</a>
</div>
<p class="muted">Encaissements confirmés (prestataire vérifié par le serveur, ou réception constatée par l'équipe) et remboursements, pour le rapprochement avec les relevés Mobile Money, bancaires et de caisse. Une déclaration de paiement d'un client n'apparaît pas ici tant que l'équipe n'a pas constaté la réception.</p>
<form class="filters inline-form" method="get" action="/admin/paiements/journal">
  <label class="sr-only" for="j-type">Type</label>
  <select id="j-type" name="type"><option value="">Tous les mouvements</option><option value="payment"<?= $f['type'] === 'payment' ? ' selected' : '' ?>>Encaissements</option><option value="refund"<?= $f['type'] === 'refund' ? ' selected' : '' ?>>Remboursements</option></select>
  <label for="j-du">Du</label><input id="j-du" name="du" type="date" value="<?= e($f['du']) ?>">
  <label for="j-au">au</label><input id="j-au" name="au" type="date" value="<?= e($f['au']) ?>">
  <button class="btn btn-ghost btn-small" type="submit">Filtrer</button>
</form>
<p class="small">Encaissé : <strong><?= money($totals['in']) ?></strong> · Remboursé : <strong><?= money($totals['out']) ?></strong> · Remboursements à effectuer : <strong><?= money($totals['pending']) ?></strong> (sur les mouvements affichés)</p>
<?php if (!$rows): ?><p class="card muted">Aucun mouvement.</p><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Date</th><th>Commande</th><th>Mouvement</th><th>Moyen</th><th class="num">Montant</th><th>Référence</th><th>Motif / note</th><th>Saisi par</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $t): ?>
  <tr>
    <td class="small"><?= e(date_fr($t['created_at'])) ?></td>
    <td><a href="/admin/commandes/<?= (int) $t['order_id'] ?>#paiements"><?= e($t['number']) ?></a></td>
    <td><?= $t['type'] === 'payment' ? 'Encaissement' : 'Remboursement' ?><?= $t['status'] === 'pending' ? ' <span class="status status-pending_payment">à effectuer</span>' : '' ?></td>
    <td class="small"><?= e($methods[$t['method']] ?? $t['method']) ?></td>
    <td class="num"><?= $t['type'] === 'refund' ? '−' : '' ?><?= money((int) $t['amount']) ?></td>
    <td class="small"><code><?= e((string) $t['reference']) ?></code></td>
    <td class="small"><?= e((string) $t['reason']) ?></td>
    <td class="small"><?= $t['first_name'] ? e($t['first_name'] . ' ' . $t['last_name']) : 'Prestataire / système' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
