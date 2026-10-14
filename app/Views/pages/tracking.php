<?php
use App\Core\View;
/** @var array $errors @var array $old @var ?array $order @var array $history */
?>
<div class="wrap narrow page-head"><h1><?= e(t('tracking.title')) ?></h1><p class="lead"><?= e(t('tracking.lead')) ?></p></div>
<div class="wrap narrow">
  <form action="/suivi-commande" method="post" class="form-card form-grid" novalidate>
    <?= csrf_field() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field">
      <label for="f-number"><?= e(t('field.order_number_short')) ?></label>
      <input id="f-number" name="number" maxlength="40" required placeholder="MPCI-20260101-XXXXXXXX" value="<?= e($old['number'] ?? '') ?>" autocapitalize="characters"<?= aria_invalid($errors, 'number') ?>>
      <?= field_error($errors, 'number') ?>
    </div>
    <div class="field">
      <label for="f-email"><?= e(t('field.email')) ?></label>
      <input id="f-email" name="email" type="email" maxlength="190" required value="<?= e($old['email'] ?? '') ?>" autocomplete="email"<?= aria_invalid($errors, 'email') ?>>
      <?= field_error($errors, 'email') ?>
    </div>
    <div class="field-full"><button class="btn" type="submit"><?= icon('search') ?><?= e(t('tracking.search')) ?></button></div>
  </form>

  <?php if ($order): ?>
  <h2 class="group-title"><?= e(t('order.title', ['number' => $order['number']])) ?></h2>
  <div class="group">
    <div class="cell"><span class="cell-icon"><?= icon('box') ?></span><span class="cell-body"><span class="cell-title"><?= e(status_label($order['status'])) ?></span><br><span class="cell-sub"><?= e(t('totals.total')) ?> : <?= money((int) $order['total']) ?></span></span></div>
  </div>
  <ul class="group timeline mt">
    <?php foreach ($history as $h): ?><li><time><?= e(date_fr($h['created_at'])) ?></time><strong><?= e(status_label($h['to_status'])) ?></strong></li><?php endforeach; ?>
  </ul>
  <p class="group-footer"><?= e(t('tracking.privacy')) ?></p>
  <?php endif; ?>
</div>
<div class="section"></div>
