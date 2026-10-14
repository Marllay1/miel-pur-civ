<?php /** @var array $orders */ ?>
<?php if (!$orders): ?>
<p class="muted">Aucune commande pour le moment. <a href="/boutique">Découvrir la boutique</a></p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><th scope="col">N°</th><th scope="col">Date</th><th scope="col">Statut</th><th scope="col" class="num">Total</th></tr></thead>
  <tbody>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><a href="/commande/<?= e($o['number']) ?>"><?= e($o['number']) ?></a></td>
      <td><?= e(date_fr($o['created_at'], false)) ?></td>
      <td><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
      <td class="num"><?= money((int) $o['total']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
