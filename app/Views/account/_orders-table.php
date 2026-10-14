<?php /** @var array $orders */ ?>
<?php if (!$orders): ?>
<div class="empty-state"><?= icon('receipt') ?><p class="muted"><?= e(t('account.no_orders')) ?></p><a class="btn btn-tinted" href="/boutique"><?= e(t('home.cta_shop')) ?></a></div>
<?php else: ?>
<div class="group">
  <?php foreach ($orders as $o): ?>
  <a class="cell" href="/commande/<?= e($o['number']) ?>">
    <span class="cell-icon gray"><?= icon('receipt') ?></span>
    <span class="cell-body"><span class="cell-title"><?= e($o['number']) ?></span><br><span class="cell-sub"><?= e(date_fr($o['created_at'], false)) ?> · <?= money((int) $o['total']) ?></span></span>
    <span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
    <span class="cell-chevron"><?= icon('chevron') ?></span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
