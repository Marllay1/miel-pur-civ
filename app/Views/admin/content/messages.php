<?php /** @var array $messages */ $labels = ['new' => 'Nouveau', 'read' => 'Lu', 'archived' => 'Archivé']; ?>
<h1>Messages de contact</h1>
<?php if (!$messages): ?><p class="muted">Aucun message.</p><?php endif; ?>
<?php foreach ($messages as $m): ?>
<article class="card<?= $m['status'] === 'new' ? ' card-new' : '' ?>">
  <div class="admin-head"><h2><?= e($m['subject']) ?></h2><span class="status"><?= e($labels[$m['status']] ?? $m['status']) ?></span></div>
  <p class="small"><?= e(date_fr($m['created_at'])) ?> · <?= e($m['name']) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?></p>
  <p><?= nl2br(e($m['message'])) ?></p>
  <form action="/admin/messages/<?= (int) $m['id'] ?>" method="post" class="inline-form">
    <?= csrf_field() ?>
    <?php foreach ($labels as $k => $l): if ($k === $m['status']) { continue; } ?>
    <button class="btn btn-ghost btn-small" type="submit" name="status" value="<?= $k ?>">Marquer « <?= e($l) ?> »</button>
    <?php endforeach; ?>
  </form>
</article>
<?php endforeach; ?>
