<?php /** @var array $pages */ ?>
<h1>Pages et documents légaux</h1>
<p class="notice notice-amber small">Les documents légaux sont des <strong>modèles provisoires</strong>. Ne cochez « validé juridiquement » qu'après relecture par un professionnel du droit ivoirien. Tant qu'ils ne sont pas validés, un avertissement est affiché aux visiteurs.</p>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Page</th><th>Adresse</th><th>Type</th><th>Validation</th><th>Mise à jour</th></tr></thead>
  <tbody>
  <?php foreach ($pages as $p): ?>
    <tr><td><a href="/admin/pages/<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a></td><td><a href="/<?= e($p['slug']) ?>" target="_blank" rel="noopener">/<?= e($p['slug']) ?></a></td>
    <td><?= (int) $p['is_legal'] ? 'Légal' : 'Contenu' ?></td>
    <td><?= (int) $p['is_legal'] ? ((int) $p['is_validated'] ? '<span class="status status-delivered">Validé</span>' : '<span class="status status-pending_payment">À valider</span>') : '—' ?></td>
    <td class="small"><?= e(date_fr($p['updated_at'])) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
