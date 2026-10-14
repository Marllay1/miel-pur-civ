<?php /** @var array $quotes @var string $status @var array $statuses @var array $activities */ ?>
<h1>Demandes de devis</h1>
<ul class="status-tabs">
  <li><a href="/admin/devis"<?= $status === '' ? ' aria-current="page"' : '' ?>>Toutes</a></li>
  <?php foreach ($statuses as $k => $l): ?><li><a href="/admin/devis?statut=<?= $k ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a></li><?php endforeach; ?>
</ul>
<?php if (!$quotes): ?><p class="muted">Aucune demande.</p><?php endif; ?>
<?php foreach ($quotes as $q): ?>
<article class="card">
  <div class="admin-head">
    <h2><?= e($q['company']) ?> <span class="muted small">· <?= e($activities[$q['activity']] ?? $q['activity']) ?></span></h2>
    <span class="status"><?= e($statuses[$q['status']] ?? $q['status']) ?></span>
  </div>
  <p class="small"><?= e(date_fr($q['created_at'])) ?> · <?= e($q['contact_name']) ?> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $q['phone'])) ?>"><?= e($q['phone']) ?></a> · <a href="mailto:<?= e($q['email']) ?>"><?= e($q['email']) ?></a><?= $q['city'] ? ' · ' . e($q['city']) : '' ?></p>
  <dl class="dl-inline small">
    <?php if ($q['products_interest']): ?><dt>Produits</dt><dd><?= e($q['products_interest']) ?></dd><?php endif; ?>
    <?php if ($q['estimated_volume']): ?><dt>Volume</dt><dd><?= e($q['estimated_volume']) ?></dd><?php endif; ?>
    <?php if ($q['frequency']): ?><dt>Fréquence</dt><dd><?= e($q['frequency']) ?></dd><?php endif; ?>
  </dl>
  <?php if ($q['message']): ?><p><?= nl2br(e($q['message'])) ?></p><?php endif; ?>
  <form action="/admin/devis/<?= (int) $q['id'] ?>" method="post" class="inline-form">
    <?= csrf_field() ?>
    <label class="sr-only" for="qs-<?= (int) $q['id'] ?>">Statut</label>
    <select id="qs-<?= (int) $q['id'] ?>" name="status"><?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>"<?= $q['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="qn-<?= (int) $q['id'] ?>">Note interne</label>
    <input id="qn-<?= (int) $q['id'] ?>" name="admin_note" maxlength="5000" placeholder="Note interne" value="<?= e($q['admin_note']) ?>">
    <button class="btn btn-ghost btn-small" type="submit">Enregistrer</button>
  </form>
</article>
<?php endforeach; ?>
