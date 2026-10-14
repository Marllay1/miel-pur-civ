<?php
/** @var array $order @var array $items @var array $history @var array $payments @var ?array $shipment @var array $transitions */
$labels = ['preparing' => 'Confirmer / mettre en préparation', 'shipped' => 'Marquer comme expédiée', 'delivered' => 'Marquer comme livrée',
    'cancelled' => 'Annuler la commande', 'refunded' => 'Marquer comme remboursée', 'paid' => 'Marquer comme payée (paiement reçu hors ligne)'];
?>
<p><a href="/admin/commandes">← Commandes</a></p>
<div class="admin-head">
  <h1><?= e($order['number']) ?></h1>
  <span class="status status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span>
</div>
<p class="muted">Passée le <?= e(date_fr($order['created_at'])) ?> · <?= e(payment_method_label($order['payment_method'])) ?> · <?= e(payment_status_label($order['payment_status'])) ?><?= $order['expires_at'] ? ' · échéance de paiement : ' . e(date_fr($order['expires_at'])) : '' ?></p>
<?php if ($order['admin_note']): ?><p class="flash flash-error"><?= nl2br(e($order['admin_note'])) ?></p><?php endif; ?>

<div class="admin-cols">
  <div>
    <section class="card">
      <h2>Articles</h2>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Article</th><th>Réf.</th><th class="num">P.U.</th><th class="num">Qté</th><th class="num">Total</th></tr></thead>
        <tbody><?php foreach ($items as $i): ?><tr><td><?= e($i['product_name'] . ' – ' . $i['variant_label']) ?></td><td><code><?= e($i['sku']) ?></code></td><td class="num"><?= money((int) $i['unit_price']) ?></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= money((int) $i['line_total']) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot>
          <tr><th colspan="4">Sous-total</th><td class="num"><?= money((int) $order['subtotal']) ?></td></tr>
          <?php if ((int) $order['discount'] > 0): ?><tr><th colspan="4">Remise <?= e($order['promo_code']) ?></th><td class="num">−<?= money((int) $order['discount']) ?></td></tr><?php endif; ?>
          <tr><th colspan="4">Livraison</th><td class="num"><?= money((int) $order['shipping_fee']) ?></td></tr>
          <tr class="grand-total"><th colspan="4">Total</th><td class="num"><?= money((int) $order['total']) ?></td></tr>
        </tfoot>
      </table></div>
      <p class="small muted">Prix figés au moment de la commande (instantané), indépendants des modifications ultérieures du catalogue.</p>
    </section>

    <section class="card">
      <h2>Changer le statut</h2>
      <?php if ($transitions): ?>
      <form action="/admin/commandes/<?= (int) $order['id'] ?>/statut" method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="field"><label for="t-status">Nouveau statut</label>
          <select id="t-status" name="status" required><?php foreach ($transitions as $t): ?><option value="<?= e($t) ?>"><?= e($labels[$t] ?? status_label($t)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="t-note">Commentaire (visible par le client)</label><input id="t-note" name="note" maxlength="1000"></div>
        <div class="field-full"><button class="btn btn-primary btn-small" type="submit">Appliquer</button> <span class="hint">L'annulation restitue le stock et le code promo.</span></div>
      </form>
      <?php else: ?><p class="muted">Aucune transition possible depuis ce statut.</p><?php endif; ?>
    </section>

    <section class="card">
      <h2>Historique</h2>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
        <li><?= e(date_fr($h['created_at'])) ?> — <strong><?= e(status_label($h['to_status'])) ?></strong><?= $h['note'] ? ' · ' . e($h['note']) : '' ?><?= $h['first_name'] ? ' <span class="muted">(' . e($h['first_name'] . ' ' . $h['last_name']) . ')</span>' : '' ?></li>
        <?php endforeach; ?>
      </ul>
      <form action="/admin/commandes/<?= (int) $order['id'] ?>/note" method="post" class="inline-form">
        <?= csrf_field() ?><label class="sr-only" for="n-note">Note interne</label>
        <input id="n-note" name="note" maxlength="2000" required placeholder="Note interne (non visible du client)">
        <button class="btn btn-ghost btn-small" type="submit">Ajouter</button>
      </form>
    </section>
  </div>

  <div>
    <section class="card">
      <h2>Client</h2>
      <p><strong><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></strong><br>
        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $order['customer_phone'])) ?>"><?= e($order['customer_phone']) ?></a><br>
        <a href="mailto:<?= e($order['customer_email']) ?>"><?= e($order['customer_email']) ?></a></p>
      <p class="small"><?= $order['user_id'] ? '<a href="/admin/clients/' . (int) $order['user_id'] . '">Compte client</a>' : 'Commande sans compte' ?></p>
      <?php if ($order['customer_note']): ?><p class="notice small"><?= nl2br(e($order['customer_note'])) ?></p><?php endif; ?>
    </section>
    <section class="card">
      <h2>Livraison</h2>
      <p><strong><?= e($order['shipping_zone_name']) ?></strong><?= (int) $order['is_pickup'] ? ' (retrait)' : '' ?></p>
      <?php if (!(int) $order['is_pickup']): ?><p><?= e($order['shipping_address']) ?><br><?= e(trim(($order['shipping_district'] ? $order['shipping_district'] . ', ' : '') . $order['shipping_city'])) ?><?= $order['shipping_landmark'] ? '<br>Repère : ' . e($order['shipping_landmark']) : '' ?></p><?php endif; ?>
      <form action="/admin/commandes/<?= (int) $order['id'] ?>/livraison" method="post">
        <?= csrf_field() ?>
        <div class="field"><label for="s-carrier">Livreur / transporteur</label><input id="s-carrier" name="carrier" maxlength="120" value="<?= e($shipment['carrier'] ?? '') ?>"></div>
        <div class="field"><label for="s-tracking">N° de suivi</label><input id="s-tracking" name="tracking_number" maxlength="120" value="<?= e($shipment['tracking_number'] ?? '') ?>"></div>
        <div class="field"><label for="s-note">Note</label><input id="s-note" name="note" maxlength="255" value="<?= e($shipment['note'] ?? '') ?>"></div>
        <button class="btn btn-ghost btn-small" type="submit">Enregistrer</button>
      </form>
      <?php if ($shipment && $shipment['shipped_at']): ?><p class="small muted">Expédiée le <?= e(date_fr($shipment['shipped_at'])) ?><?= $shipment['delivered_at'] ? ' · livrée le ' . e(date_fr($shipment['delivered_at'])) : '' ?></p><?php endif; ?>
    </section>
    <section class="card">
      <h2>Paiements</h2>
      <?php if (!$payments): ?><p class="muted small">Aucune tentative de paiement en ligne.</p><?php endif; ?>
      <ul class="plain-list small">
        <?php foreach ($payments as $p): ?>
        <li><code><?= e($p['reference']) ?></code> · <?= e(payment_method_label($p['provider'])) ?> · <?= money((int) $p['amount']) ?> · <strong><?= e($p['status']) ?></strong><br><span class="muted"><?= e(date_fr($p['updated_at'])) ?><?= $p['provider_reference'] ? ' · réf. prestataire ' . e($p['provider_reference']) : '' ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <p><a class="btn btn-ghost btn-small" href="/commande/<?= e($order['number']) ?>/recu" target="_blank" rel="noopener">Récapitulatif imprimable</a></p>
  </div>
</div>
