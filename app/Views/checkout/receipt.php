<?php
use App\Services\Settings;
/** @var array $order @var array $items @var string $token */
?>
<div class="receipt">
  <header class="receipt-head">
    <div>
      <p class="receipt-store"><?= e(Settings::get('store_name')) ?></p>
      <p class="small"><?= rich_inline(Settings::get('store_address')) ?> — <?= rich_inline(Settings::get('store_city')) ?><br>Tél. <?= rich_inline(Settings::get('store_phone')) ?> · <?= rich_inline(Settings::get('store_email')) ?><br>RCCM <?= rich_inline(Settings::get('legal_rccm')) ?> · NCC <?= rich_inline(Settings::get('legal_ncc')) ?></p>
    </div>
    <div class="receipt-meta">
      <p><strong>Récapitulatif de commande</strong></p>
      <p><?= e($order['number']) ?><br><?= e(date_fr($order['created_at'])) ?></p>
    </div>
  </header>
  <p class="receipt-warning">Ce document récapitule la commande. Il ne constitue pas une facture. [À CONFIRMER : modèle de facture conforme à fournir]</p>
  <div class="receipt-parties">
    <div><p class="small muted">Client</p><p><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?><br><?= e($order['customer_phone']) ?><br><?= e($order['customer_email']) ?></p></div>
    <div><p class="small muted">Livraison</p><p><?= e($order['shipping_zone_name']) ?><?php if (!(int) $order['is_pickup']): ?><br><?= e($order['shipping_address']) ?><br><?= e(trim(($order['shipping_district'] ? $order['shipping_district'] . ', ' : '') . $order['shipping_city'])) ?><?php endif; ?></p></div>
    <div><p class="small muted">Paiement</p><p><?= e(payment_method_label($order['payment_method'])) ?><br><?= e(payment_status_label($order['payment_status'])) ?><br>Statut : <?= e(status_label($order['status'])) ?></p></div>
  </div>
  <table class="table">
    <thead><tr><th scope="col">Article</th><th scope="col">Réf.</th><th scope="col" class="num">P.U.</th><th scope="col" class="num">Qté</th><th scope="col" class="num">Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $i): ?>
      <tr><td><?= e($i['product_name'] . ' – ' . $i['variant_label']) ?></td><td><?= e($i['sku']) ?></td><td class="num"><?= money((int) $i['unit_price']) ?></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= money((int) $i['line_total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th scope="row" colspan="4">Sous-total</th><td class="num"><?= money((int) $order['subtotal']) ?></td></tr>
      <?php if ((int) $order['discount'] > 0): ?><tr><th scope="row" colspan="4">Remise</th><td class="num">−<?= money((int) $order['discount']) ?></td></tr><?php endif; ?>
      <tr><th scope="row" colspan="4">Livraison</th><td class="num"><?= money((int) $order['shipping_fee']) ?></td></tr>
      <tr class="grand-total"><th scope="row" colspan="4">Total</th><td class="num"><?= money((int) $order['total']) ?></td></tr>
    </tfoot>
  </table>
  <p class="receipt-actions no-print">
    <button class="btn btn-primary" type="button" data-print>Imprimer</button>
    <a class="btn btn-ghost" href="<?= e(url('/commande/' . $order['number'], ['t' => $token])) ?>">Retour à la commande</a>
  </p>
</div>
