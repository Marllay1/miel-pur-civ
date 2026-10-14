<?php
use App\Core\View;
/** @var array $messages @var array $counts @var array $f @var int $total @var int $page @var int $pages @var array $statuses @var array $reasons @var array $notify */
?>
<h1>Messages de contact</h1>
<p class="muted">Messages envoyés depuis le formulaire « Contactez-nous » du site (page Contact), enregistrés en base de données.</p>
<p class="notice small<?= $notify['ok'] ? ' notice-green' : ' notice-amber' ?>"><?= e($notify['text']) ?></p>
<nav class="site-tabs" aria-label="Filtrer par statut">
  <a href="/admin/messages"<?= $f['statut'] === '' ? ' aria-current="page"' : '' ?>>À traiter (<?= array_sum($counts) - ($counts['archived'] ?? 0) ?>)</a>
  <?php foreach ($statuses as $k => $l): ?><a href="<?= e(url('/admin/messages', ['statut' => $k])) ?>"<?= $f['statut'] === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> (<?= (int) ($counts[$k] ?? 0) ?>)</a><?php endforeach; ?>
</nav>
<form class="filters inline-form" method="get" action="/admin/messages">
  <?php if ($f['statut'] !== ''): ?><input type="hidden" name="statut" value="<?= e($f['statut']) ?>"><?php endif; ?>
  <label class="sr-only" for="m-q">Rechercher</label><input id="m-q" name="q" type="search" maxlength="80" placeholder="Nom, e-mail, objet, texte…" value="<?= e($f['q']) ?>">
  <label class="sr-only" for="m-motif">Motif</label>
  <select id="m-motif" name="motif"><option value="">Tous les motifs</option><?php foreach ($reasons as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['motif'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <button class="btn btn-ghost btn-small" type="submit">Rechercher</button>
</form>
<?php if (!$messages): ?><p class="card muted">Aucun message.</p><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Date</th><th>De</th><th>Objet</th><th>Motif</th><th>Statut</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($messages as $m): ?>
  <tr<?= $m['status'] === 'new' ? ' class="row-new"' : '' ?>>
    <td class="small"><?= e(date_fr($m['created_at'])) ?></td>
    <td><?= e($m['name']) ?><br><span class="small muted"><?= e($m['email']) ?><?= $m['user_id'] ? ' · client inscrit' : '' ?></span></td>
    <td><a href="/admin/messages/<?= (int) $m['id'] ?>"><?= $m['status'] === 'new' ? '<strong>' . e($m['subject']) . '</strong>' : e($m['subject']) ?></a><?= (int) $m['reply_count'] > 0 ? ' <span class="small muted">· ' . (int) $m['reply_count'] . ' réponse(s)</span>' : '' ?></td>
    <td class="small"><?= e($reasons[$m['reason']] ?? $m['reason']) ?></td>
    <td><span class="status status-msg-<?= e($m['status']) ?>"><?= e($statuses[$m['status']] ?? $m['status']) ?></span></td>
    <td><a class="btn btn-ghost btn-small" href="/admin/messages/<?= (int) $m['id'] ?>">Ouvrir</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= View::partial('pagination', ['page' => $page, 'pages' => $pages, 'base' => '/admin/messages', 'query' => array_filter($f)]) ?>
<?php endif; ?>
