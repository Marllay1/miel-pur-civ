<?php /** @var array $payment @var array $order @var string $token */ ?>
<div class="wrap narrow page-head">
  <div class="simulated" role="alert"><strong><?= e(t('pay.demo')) ?></strong><br><?= e(t('pay.demo_text')) ?></div>
  <div class="group summary">
    <dl class="totals">
      <div><dt><?= e(t('order.label')) ?></dt><dd><?= e($order['number']) ?></dd></div>
      <div><dt><?= e(t('pay.reference')) ?></dt><dd><?= e($payment['reference']) ?></dd></div>
      <div class="grand-total"><dt><?= e(t('pay.amount')) ?></dt><dd><?= money((int) $payment['amount']) ?></dd></div>
    </dl>
    <?php if ($payment['status'] === 'initiated'): ?>
    <form action="/paiement/demo/<?= e($payment['reference']) ?>" method="post" class="btn-row">
      <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <button class="btn btn-green" type="submit" name="outcome" value="succeeded"><?= e(t('pay.demo_success')) ?></button>
      <button class="btn btn-destructive" type="submit" name="outcome" value="failed"><?= e(t('pay.demo_fail')) ?></button>
      <button class="btn btn-plain" type="submit" name="outcome" value="cancelled"><?= e(t('pay.demo_cancel')) ?></button>
    </form>
    <?php else: ?>
    <p><?= e(t('pay.result.already')) ?></p>
    <a class="btn btn-gray" href="<?= e(url('/commande/' . $order['number'], ['t' => $token])) ?>"><?= e(t('pay.wait_back')) ?></a>
    <?php endif; ?>
  </div>
</div>
<div class="section"></div>
