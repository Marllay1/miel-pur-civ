<?php
/** @var array $order @var string $token @var array $items @var array $history @var ?array $shipment @var bool $canPay @var bool $needsPhone @var ?array $lastPayment @var ?array $manual @var int $dueNow @var int $balance */
$flow = match ($order['payment_terms']) {
    'cod', 'invoice' => ['pending_confirmation', 'preparing', 'shipped', 'delivered'],
    'deposit' => ['pending_payment', 'deposit_paid', 'preparing', 'shipped', 'delivered'],
    default => ['pending_payment', 'paid', 'preparing', 'shipped', 'delivered'],
};
$isDepositStep = $order['payment_terms'] === 'deposit' && (int) $order['amount_paid'] < (int) $order['deposit_amount'];
$payLabel = $order['payment_terms'] === 'deposit' ? t($isDepositStep ? 'order.pay_deposit' : 'order.pay_balance') : t('order.manual_amount');
if ((int) $order['is_pickup'] === 1) {
    $flow = array_values(array_diff($flow, ['shipped']));
}
$reached = array_column($history, 'to_status');
$closed = in_array($order['status'], ['cancelled', 'refunded'], true);
$currentIndex = array_search($order['status'], $flow, true);
?>
<div class="wrap page-head">
  <p class="product-card-cat"><?= e(t('order.label')) ?></p>
  <h1><?= e($order['number']) ?></h1>
  <p class="muted"><?= e(t('order.placed_on', ['date' => date_fr($order['created_at'])])) ?> · <span class="status status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></p>
</div>
<div class="wrap order-layout">
  <div>
    <?php if ($order['status'] === 'pending_confirmation' && $order['payment_terms'] === 'invoice'): ?>
    <div class="notice notice-green"><?= icon('info') ?><p><?= e(t('order.invoice_notice', ['total' => money((int) $order['total']), 'date' => $order['balance_due_at'] ? date_fr($order['balance_due_at'], false) : '—'])) ?></p></div>
    <?php elseif ($order['status'] === 'pending_confirmation'): ?>
    <div class="notice notice-green"><?= icon('phone') ?><p><?= e(t('order.cod_notice', ['phone' => $order['customer_phone'], 'total' => money((int) $order['total'])])) ?></p></div>
    <?php elseif (!$closed && $manual !== null && $dueNow > 0): ?>
    <div class="group order-status-card manual-pay">
      <h2><?= e(t('order.manual_title', ['method' => payment_method_label($order['payment_method'])])) ?></h2>
      <?= payment_logos(\App\Services\PaymentMethods::logos($manual), payment_method_label($order['payment_method'])) ?>
      <dl class="manual-details">
        <div><dt><?= e($payLabel) ?></dt><dd><strong><?= money($dueNow) ?></strong></dd></div>
        <?php if ((string) $manual['receiver_number'] !== ''): ?><div><dt><?= e(t('order.manual_receiver')) ?></dt><dd><strong><?= e($manual['receiver_number']) ?></strong></dd></div><?php endif; ?>
        <?php if ((string) $manual['merchant_number'] !== ''): ?><div><dt><?= e(t('order.manual_merchant')) ?></dt><dd><?= e($manual['merchant_number']) ?></dd></div><?php endif; ?>
        <?php if ((string) $manual['beneficiary'] !== ''): ?><div><dt><?= e(t('order.manual_beneficiary')) ?></dt><dd><?= e($manual['beneficiary']) ?></dd></div><?php endif; ?>
        <div><dt><?= e(t('order.manual_reference')) ?></dt><dd><strong><?= e($order['number']) ?></strong></dd></div>
      </dl>
      <?php if (\App\Services\PaymentMethods::instructions($manual) !== ''): ?><div class="prose"><?= rich(\App\Services\PaymentMethods::instructions($manual)) ?></div><?php endif; ?>
      <p class="notice"><?= icon('info') ?> <?= e(t('order.manual_notice')) ?></p>
      <?php if ($order['payment_declared_at'] !== null): ?>
      <p class="notice notice-green"><?= icon('check') ?> <?= e(t('order.declared_on', ['date' => date_fr($order['payment_declared_at']), 'reference' => (string) $order['payment_declaration']])) ?></p>
      <?php else: ?>
      <form action="/commande/<?= e($order['number']) ?>/paiement-effectue" method="post" class="declare-form">
        <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
        <div class="field"><label for="f-declare-ref"><?= e(t('order.declare_ref')) ?></label>
          <input id="f-declare-ref" name="reference" maxlength="120" required autocomplete="off"></div>
        <button class="btn btn-gray" type="submit"><?= e(t('order.declare_button')) ?></button>
        <p class="small muted"><?= e(t('order.declare_hint')) ?></p>
      </form>
      <?php endif; ?>
    </div>
    <?php elseif ($order['status'] === 'pending_payment' || $canPay): ?>
    <div class="group order-status-card">
      <?php if ($canPay): ?>
      <h2><?= e($order['payment_terms'] === 'deposit' ? $payLabel : t('order.pay_title')) ?></h2>
      <?php if ($order['status'] === 'pending_payment' && $order['expires_at']): ?><p class="muted"><?= e(t('order.pay_deadline', ['date' => date_fr($order['expires_at'])])) ?></p><?php endif; ?>
      <?php if ($order['payment_status'] === 'failed'): ?><p class="notice notice-amber"><?= icon('info') ?> <?= e(t('order.pay_failed')) ?></p><?php endif; ?>
      <form action="/commande/<?= e($order['number']) ?>/payer" method="post">
        <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
        <?php if ($needsPhone): ?>
        <div class="field">
          <label for="f-payer_phone"><?= e(t('field.payer_phone_short')) ?></label>
          <input id="f-payer_phone" name="payer_phone" type="tel" inputmode="tel" maxlength="20" required value="<?= e($lastPayment['payer_phone'] ?? $order['customer_phone']) ?>">
        </div>
        <?php endif; ?>
        <button class="btn btn-large btn-block" type="submit"><?= pay_logo($order['payment_method']) ?> <?= e(t('order.pay_with', ['method' => payment_method_label($order['payment_method']), 'total' => money($dueNow)])) ?></button>
      </form>
      <?php else: ?>
      <p><?= e(t('order.pay_unavailable')) ?></p>
      <?php endif; ?>
    </div>
    <?php elseif ($order['status'] === 'cancelled'): ?>
    <div class="notice"><?= icon('info') ?><p><?= e(t('order.cancelled_on', ['date' => date_fr($order['cancelled_at'])])) ?></p></div>
    <?php endif; ?>

    <?php if (!$closed): ?>
    <h2 class="group-title"><?= e(t('order.progress')) ?></h2>
    <div class="group order-status-card">
      <ol class="order-progress">
        <?php foreach ($flow as $i => $s): $done = $currentIndex !== false && $i < $currentIndex || in_array($s, $reached, true) && $s !== $order['status']; ?>
        <li class="<?= $done ? 'is-done' : '' ?><?= $order['status'] === $s ? ' is-current' : '' ?>"<?= $order['status'] === $s ? ' aria-current="step"' : '' ?>><?= e(status_label($s)) ?></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php endif; ?>

    <h2 class="group-title"><?= e(t('order.items')) ?></h2>
    <div class="group table-wrap">
      <table class="table">
        <caption class="sr-only"><?= e(t('order.items')) ?></caption>
        <thead><tr><th scope="col"><?= e(t('order.product')) ?></th><th scope="col" class="num"><?= e(t('order.qty')) ?></th><th scope="col" class="num"><?= e(t('totals.total')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($items as $i): ?>
          <tr><td><div class="order-line"><?php if (!empty($i['image_path'])): ?><img class="mini-thumb" src="<?= e($i['image_path']) ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><?php endif; ?><span><?= e($i['product_name']) ?><br><span class="muted small"><?= e($i['variant_label']) ?> · <?= money((int) $i['unit_price']) ?></span></span></div></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= money((int) $i['line_total']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><th scope="row" colspan="2"><?= e(t('totals.subtotal')) ?></th><td class="num"><?= money((int) $order['subtotal']) ?></td></tr>
          <?php if ((int) $order['discount'] > 0): ?><tr><th scope="row" colspan="2"><?= e(t('totals.discount')) ?><?= $order['promo_code'] ? ' (' . e($order['promo_code']) . ')' : '' ?></th><td class="num">−<?= money((int) $order['discount']) ?></td></tr><?php endif; ?>
          <tr><th scope="row" colspan="2"><?= e(t('totals.shipping')) ?> · <?= e($order['shipping_zone_name']) ?></th><td class="num"><?= (int) $order['shipping_fee'] === 0 ? e(t('checkout.free')) : money((int) $order['shipping_fee']) ?></td></tr>
          <tr class="grand-total"><th scope="row" colspan="2"><?= e(t('totals.total')) ?></th><td class="num"><?= money((int) $order['total']) ?></td></tr>
        </tfoot>
      </table>
    </div>

    <?php if ($history): ?>
    <h2 class="group-title"><?= e(t('order.history')) ?></h2>
    <ul class="group timeline">
      <?php foreach ($history as $h): ?>
      <li><time datetime="<?= e(str_replace(' ', 'T', $h['created_at'])) ?>Z"><?= e(date_fr($h['created_at'])) ?></time><strong><?= e(status_label($h['to_status'])) ?></strong><?= $h['user_id'] !== null && $h['note'] ? ' · ' . e($h['note']) : '' ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

  <aside class="sticky-aside">
    <h2 class="group-title"><?= e(t('order.delivery')) ?></h2>
    <div class="group">
      <div class="cell"><span class="cell-icon"><?= icon('truck') ?></span><span class="cell-body"><span class="cell-title"><?= e($order['shipping_zone_name']) ?></span><br>
        <span class="cell-sub"><?php if ((int) $order['is_pickup'] === 1): ?><?= e(t('order.pickup')) ?><?php else: ?><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?>, <?= e($order['shipping_address']) ?>, <?= e(trim(($order['shipping_district'] ? $order['shipping_district'] . ', ' : '') . $order['shipping_city'])) ?><?php if ($order['shipping_landmark']): ?> · <?= e($order['shipping_landmark']) ?><?php endif; ?><?php endif; ?></span></span></div>
      <?php if ($shipment && ($shipment['carrier'] || $shipment['tracking_number'])): ?>
      <div class="cell"><span class="cell-icon gray"><?= icon('box') ?></span><span class="cell-body"><span class="cell-title"><?= e($shipment['carrier'] ?: '—') ?></span><br><span class="cell-sub"><?= e($shipment['tracking_number']) ?></span></span></div>
      <?php endif; ?>
    </div>
    <h2 class="group-title"><?= e(t('order.payment')) ?></h2>
    <div class="group">
      <div class="cell"><?= pay_logo($order['payment_method']) ?><span class="cell-body"><span class="cell-title"><?= e(payment_method_label($order['payment_method'])) ?></span><br><span class="cell-sub"><?= e(payment_status_label($order['payment_status'])) ?></span></span></div>
      <dl class="totals pay-amounts">
        <div><dt><?= e(t('totals.total')) ?></dt><dd><?= money((int) $order['total']) ?></dd></div>
        <?php if ($order['deposit_amount'] !== null): ?><div><dt><?= e(t('order.deposit')) ?></dt><dd><?= money((int) $order['deposit_amount']) ?></dd></div><?php endif; ?>
        <div><dt><?= e(t('order.amount_paid')) ?></dt><dd data-amount-paid><?= money((int) $order['amount_paid']) ?></dd></div>
        <?php if ((int) $order['amount_refunded'] > 0): ?><div><dt><?= e(t('order.amount_refunded')) ?></dt><dd><?= money((int) $order['amount_refunded']) ?></dd></div><?php endif; ?>
        <?php if (!$closed): ?><div class="grand-total"><dt><?= e(t('order.amount_due')) ?></dt><dd data-amount-due><?= money($balance) ?></dd></div><?php endif; ?>
        <?php if (!$closed && $balance > 0 && $order['balance_due_at']): ?><div><dt><?= e(t('order.due_date')) ?></dt><dd><?= e(date_fr($order['balance_due_at'], false)) ?></dd></div><?php endif; ?>
      </dl>
    </div>
    <div class="btn-row mt">
      <a class="btn btn-gray btn-block" href="<?= e(url('/commande/' . $order['number'] . '/recu', ['t' => $token])) ?>"><?= icon('receipt') ?><?= e(t('order.receipt')) ?></a>
    </div>
    <?php if ($token !== ''): ?><p class="group-footer"><?= e(t('order.keep_link')) ?></p><?php endif; ?>
  </aside>
</div>
