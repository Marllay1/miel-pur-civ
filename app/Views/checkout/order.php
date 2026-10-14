<?php
/** @var array $order @var string $token @var array $items @var array $history @var ?array $shipment @var bool $canPay */
$flow = $order['payment_method'] === 'cod'
    ? ['pending_confirmation', 'preparing', 'shipped', 'delivered']
    : ['pending_payment', 'paid', 'preparing', 'shipped', 'delivered'];
if ((int) $order['is_pickup'] === 1) {
    $flow = array_values(array_diff($flow, ['shipped']));
}
$reached = array_column($history, 'to_status');
$closed = in_array($order['status'], ['cancelled', 'refunded'], true);
?>
<div class="page-head">
  <div class="wrap">
    <p class="eyebrow">Commande</p>
    <h1><?= e($order['number']) ?></h1>
    <p>Passée le <?= e(date_fr($order['created_at'])) ?> (heure d'Abidjan) · <span class="status status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></p>
  </div>
</div>
<div class="wrap order-layout">
  <div>
    <?php if (!$closed): ?>
    <ol class="order-progress" aria-label="Avancement de la commande">
      <?php foreach ($flow as $s): $done = in_array($s, $reached, true) || $order['status'] === $s; ?>
      <li class="<?= $done ? 'is-done' : '' ?><?= $order['status'] === $s ? ' is-current' : '' ?>"<?= $order['status'] === $s ? ' aria-current="step"' : '' ?>>
        <span class="hex-num" aria-hidden="true"></span><?= e(status_label($s)) ?>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <?php if ($order['status'] === 'pending_confirmation'): ?>
    <div class="notice">
      <p><strong>Paiement à la livraison.</strong> Notre équipe va vous appeler au <?= e($order['customer_phone']) ?> pour confirmer la commande et convenir de la livraison. Vous réglerez <?= money((int) $order['total']) ?> à la réception.</p>
    </div>
    <?php elseif ($order['status'] === 'pending_payment'): ?>
    <div class="notice notice-amber">
      <?php if ($canPay): ?>
      <p><strong>En attente de paiement.</strong> Cette commande sera annulée automatiquement si elle n'est pas payée avant le <?= e(date_fr($order['expires_at'])) ?>.</p>
      <?php if ($order['payment_status'] === 'failed'): ?><p class="stock-out">La dernière tentative de paiement a échoué.</p><?php endif; ?>
      <form action="/commande/<?= e($order['number']) ?>/payer" method="post">
        <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
        <button class="btn btn-primary btn-large" type="submit">Payer <?= money((int) $order['total']) ?><?= $order['payment_method'] === 'demo' ? ' (paiement SIMULÉ)' : '' ?></button>
      </form>
      <?php else: ?>
      <p><strong>Paiement en ligne indisponible</strong> pour cette commande. Contactez-nous pour la finaliser.</p>
      <?php endif; ?>
    </div>
    <?php elseif ($order['status'] === 'cancelled'): ?>
    <div class="notice"><p>Cette commande a été annulée<?= $order['cancelled_at'] ? ' le ' . e(date_fr($order['cancelled_at'])) : '' ?>.</p></div>
    <?php endif; ?>

    <section aria-labelledby="h-items">
      <h2 id="h-items">Articles</h2>
      <table class="table">
        <caption class="sr-only">Articles commandés</caption>
        <thead><tr><th scope="col">Produit</th><th scope="col" class="num">Prix</th><th scope="col" class="num">Qté</th><th scope="col" class="num">Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $i): ?>
          <tr><td><?= e($i['product_name']) ?> <span class="muted">– <?= e($i['variant_label']) ?></span></td><td class="num"><?= money((int) $i['unit_price']) ?></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= money((int) $i['line_total']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><th scope="row" colspan="3">Sous-total</th><td class="num"><?= money((int) $order['subtotal']) ?></td></tr>
          <?php if ((int) $order['discount'] > 0): ?><tr><th scope="row" colspan="3">Remise<?= $order['promo_code'] ? ' (' . e($order['promo_code']) . ')' : '' ?></th><td class="num">−<?= money((int) $order['discount']) ?></td></tr><?php endif; ?>
          <tr><th scope="row" colspan="3">Livraison (<?= e($order['shipping_zone_name']) ?>)</th><td class="num"><?= (int) $order['shipping_fee'] === 0 ? 'Offerte' : money((int) $order['shipping_fee']) ?></td></tr>
          <tr class="grand-total"><th scope="row" colspan="3">Total</th><td class="num"><?= money((int) $order['total']) ?></td></tr>
        </tfoot>
      </table>
    </section>

    <?php if ($history): ?>
    <section aria-labelledby="h-history">
      <h2 id="h-history">Historique</h2>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
        <li><time datetime="<?= e(str_replace(' ', 'T', $h['created_at'])) ?>Z"><?= e(date_fr($h['created_at'])) ?></time> — <strong><?= e(status_label($h['to_status'])) ?></strong><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>

  <aside class="order-aside">
    <section class="card">
      <h2>Livraison</h2>
      <p><strong><?= e($order['shipping_zone_name']) ?></strong></p>
      <?php if ((int) $order['is_pickup'] === 1): ?>
      <p>Retrait sur rendez-vous.</p>
      <?php else: ?>
      <p><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?><br><?= e($order['shipping_address']) ?><br><?= e(trim(($order['shipping_district'] ? $order['shipping_district'] . ', ' : '') . $order['shipping_city'])) ?><?php if ($order['shipping_landmark']): ?><br>Repère : <?= e($order['shipping_landmark']) ?><?php endif; ?></p>
      <?php endif; ?>
      <?php if ($shipment && ($shipment['carrier'] || $shipment['tracking_number'])): ?>
      <p>Transporteur : <?= e($shipment['carrier'] ?: '—') ?><?php if ($shipment['tracking_number']): ?><br>N° de suivi : <?= e($shipment['tracking_number']) ?><?php endif; ?></p>
      <?php endif; ?>
    </section>
    <section class="card">
      <h2>Paiement</h2>
      <p><?= e(payment_method_label($order['payment_method'])) ?><br><span class="muted"><?= e(payment_status_label($order['payment_status'])) ?></span></p>
      <?php if ($order['payment_method'] === 'demo'): ?><p class="small"><span class="badge badge-demo">SIMULÉ</span> Aucune somme réelle n'est encaissée.</p><?php endif; ?>
    </section>
    <p><a class="btn btn-ghost btn-block" href="<?= e(url('/commande/' . $order['number'] . '/recu', ['t' => $token])) ?>">Récapitulatif imprimable</a></p>
    <?php if ($token !== ''): ?><p class="muted small">Conservez ce lien : il permet de retrouver votre commande sans compte. Ne le partagez pas.</p><?php endif; ?>
  </aside>
</div>
