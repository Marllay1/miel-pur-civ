<?php
use App\Services\Settings;
/** @var array $order @var array $items @var string $token */
$legal = array_filter([Settings::get('legal_company'), Settings::get('legal_rccm') !== '' ? 'RCCM ' . Settings::get('legal_rccm') : '', Settings::get('legal_ncc') !== '' ? 'NCC ' . Settings::get('legal_ncc') : '']);
?>
<div class="receipt">
  <header class="receipt-head">
    <div>
      <p class="receipt-store"><?= e(Settings::get('store_name')) ?></p>
      <p class="small"><?= e(implode(', ', array_filter([Settings::get('store_address'), Settings::get('store_city'), Settings::get('store_country')]))) ?><br><?= e(\App\Services\SiteConfig::internationalPhone(Settings::get('store_phone'))) ?><?= Settings::get('store_email') !== '' ? ' · ' . e(Settings::get('store_email')) : '' ?><?php if ($legal): ?><br><?= e(implode(' · ', $legal)) ?><?php endif; ?></p>
    </div>
    <div class="receipt-meta">
      <p><strong><?= e(t('receipt.heading')) ?></strong></p>
      <p><?= e($order['number']) ?><br><?= e(date_fr($order['created_at'])) ?></p>
    </div>
  </header>
  <div class="receipt-parties">
    <div><p class="small muted"><?= e(t('receipt.customer')) ?></p><p><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?><br><?= e($order['customer_phone']) ?><br><?= e($order['customer_email']) ?></p></div>
    <div><p class="small muted"><?= e(t('order.delivery')) ?></p><p><?= e($order['shipping_zone_name']) ?><?php if (!(int) $order['is_pickup']): ?><br><?= e($order['shipping_address']) ?><br><?= e(trim(($order['shipping_district'] ? $order['shipping_district'] . ', ' : '') . $order['shipping_city'])) ?><?php endif; ?></p></div>
    <div><p class="small muted"><?= e(t('order.payment')) ?></p><p><?= e(payment_method_label($order['payment_method'])) ?><br><?= e(payment_status_label($order['payment_status'])) ?><br><?= e(status_label($order['status'])) ?></p></div>
  </div>
  <table class="table">
    <thead><tr><th scope="col"><?= e(t('order.product')) ?></th><th scope="col"><?= e(t('receipt.ref')) ?></th><th scope="col" class="num"><?= e(t('receipt.unit')) ?></th><th scope="col" class="num"><?= e(t('order.qty')) ?></th><th scope="col" class="num"><?= e(t('totals.total')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($items as $i): ?>
      <tr><td><?= e($i['product_name'] . ' – ' . $i['variant_label']) ?></td><td><?= e($i['sku']) ?></td><td class="num"><?= money((int) $i['unit_price']) ?></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= money((int) $i['line_total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th scope="row" colspan="4"><?= e(t('totals.subtotal')) ?></th><td class="num"><?= money((int) $order['subtotal']) ?></td></tr>
      <?php if ((int) $order['discount'] > 0): ?><tr><th scope="row" colspan="4"><?= e(t('totals.discount')) ?></th><td class="num">−<?= money((int) $order['discount']) ?></td></tr><?php endif; ?>
      <tr><th scope="row" colspan="4"><?= e(t('totals.shipping')) ?></th><td class="num"><?= money((int) $order['shipping_fee']) ?></td></tr>
      <tr class="grand-total"><th scope="row" colspan="4"><?= e(t('totals.total')) ?></th><td class="num"><?= money((int) $order['total']) ?></td></tr>
    </tfoot>
  </table>
  <p class="small muted"><?= e(t('receipt.note')) ?></p>
  <p class="receipt-actions no-print">
    <button class="btn" type="button" data-print><?= e(t('receipt.print')) ?></button>
    <a class="btn btn-gray" href="<?= e(url('/commande/' . $order['number'], ['t' => $token])) ?>"><?= e(t('receipt.back')) ?></a>
  </p>
</div>
