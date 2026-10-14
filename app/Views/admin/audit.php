<?php use App\Core\View; /** @var array $rows @var string $action @var array $actions @var int $page @var int $pages @var int $total */ ?>
<h1>Journal d'audit <span class="muted">(<?= $total ?>)</span></h1>
<form class="toolbar" method="get" action="/admin/journal">
  <label for="a-action">Action</label>
  <select id="a-action" name="action"><option value="">Toutes</option><?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= $action === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select>
  <button class="btn btn-ghost btn-small" type="submit">Filtrer</button>
</form>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Date (UTC)</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Détails</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr><td class="small"><?= e($row['created_at']) ?></td><td class="small"><?= $row['email'] ? e($row['first_name'] . ' ' . $row['last_name']) : '<span class="muted">système / anonyme</span>' ?></td>
    <td><code><?= e($row['action']) ?></code></td><td class="small"><?= e(trim(($row['entity_type'] ?? '') . ' ' . ($row['entity_id'] ?? ''))) ?></td>
    <td class="small mono details-cell"><?= e($row['details']) ?></td><td class="small"><?= e($row['ip']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">Aucune entrée.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?= View::partial('pagination', ['page' => $page, 'pages' => $pages, 'base' => '/admin/journal', 'query' => array_filter(['action' => $action])]) ?>
