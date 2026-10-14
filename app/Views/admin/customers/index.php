<?php /** @var array $customers @var string $q */ ?>
<h1>Clients</h1>
<p class="muted small">Comptes clients enregistrés. Les commandes passées sans compte figurent dans <a href="/admin/commandes">Commandes</a>. Données personnelles : consultation tracée dans le journal d'audit.</p>
<form class="toolbar" method="get" action="/admin/clients">
  <label class="sr-only" for="q">Recherche</label><input id="q" name="q" type="search" placeholder="Nom, e-mail, téléphone" value="<?= e($q) ?>">
  <button class="btn btn-ghost btn-small" type="submit">Rechercher</button>
</form>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Client</th><th>Contact</th><th>Inscrit le</th><th class="num">Commandes</th><th class="num">Payé</th></tr></thead>
  <tbody>
  <?php foreach ($customers as $c): ?>
    <tr><td><a href="/admin/clients/<?= (int) $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></a><?= (int) $c['is_active'] ? '' : ' <span class="muted">(désactivé)</span>' ?></td>
    <td class="small"><?= e($c['email']) ?><br><?= e($c['phone']) ?></td><td class="small"><?= e(date_fr($c['created_at'], false)) ?></td>
    <td class="num"><?= (int) $c['order_count'] ?></td><td class="num"><?= money((int) $c['paid_total']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$customers): ?><tr><td colspan="5" class="muted">Aucun client.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
