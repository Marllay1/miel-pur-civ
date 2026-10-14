<?php use App\Core\Auth; /** @var array $customer @var array $orders @var array $addresses */ ?>
<p><a href="/admin/clients">← Clients</a></p>
<h1><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h1>
<div class="admin-cols">
  <section class="card">
    <h2>Commandes</h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>N°</th><th>Date</th><th>Statut</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr><td><?= Auth::can('orders.manage') ? '<a href="/admin/commandes/' . (int) $o['id'] . '">' . e($o['number']) . '</a>' : e($o['number']) ?></td><td><?= e(date_fr($o['created_at'], false)) ?></td>
        <td><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td><td class="num"><?= money((int) $o['total']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="4" class="muted">Aucune commande.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
  <section class="card">
    <h2>Coordonnées</h2>
    <p><?= e($customer['email']) ?><br><?= e($customer['phone'] ?? '—') ?></p>
    <p class="small muted">Inscrit le <?= e(date_fr($customer['created_at'])) ?> · dernière connexion : <?= e(date_fr($customer['last_login_at'])) ?> · e-mails marketing : <?= (int) $customer['marketing_opt_in'] ? 'oui' : 'non' ?></p>
    <h3>Adresses</h3>
    <?php foreach ($addresses as $a): ?><p class="small"><strong><?= e($a['label']) ?></strong> — <?= e($a['address_line'] . ', ' . ($a['district'] ? $a['district'] . ', ' : '') . $a['city']) ?></p><?php endforeach; ?>
    <?php if (!$addresses): ?><p class="muted small">Aucune adresse.</p><?php endif; ?>
  </section>
</div>
