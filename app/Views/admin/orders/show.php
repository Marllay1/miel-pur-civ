<?php
/** @var array $order @var array $items @var array $history @var array $payments @var ?array $shipment @var array $transitions @var array $transactions @var int $balance @var int $refundable @var array $ledgerMethods @var ?array $pro */
$labels = ['preparing' => 'Confirmer / mettre en préparation', 'shipped' => 'Marquer comme expédiée', 'delivered' => 'Marquer comme livrée',
    'cancelled' => 'Annuler la commande', 'refunded' => 'Rembourser le montant encaissé et marquer remboursée', 'paid' => 'Paiement reçu : encaisser le reste dû (' . money($balance) . ')'];
$termsLabels = ['full' => 'Paiement intégral avant expédition', 'deposit' => 'Acompte puis solde', 'cod' => 'Paiement à la livraison', 'invoice' => 'Paiement sur facture (professionnel autorisé)'];
$closed = in_array($order['status'], ['cancelled', 'refunded'], true);
$shipBlocked = $order['status'] === 'preparing' && !\App\Services\OrderService::canShip($order);
$collectable = $balance > 0 && in_array('delivered', $transitions, true);
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
        <?php if ($collectable): ?>
        <label class="check field-full"><input type="checkbox" name="collected" value="1"> À la livraison : encaissement de <?= money($balance) ?> confirmé (sinon la commande reste « à encaisser »)</label>
        <?php endif; ?>
        <div class="field-full"><button class="btn btn-primary btn-small" type="submit">Appliquer</button> <span class="hint">L'annulation restitue le stock et le code promo ; un montant déjà encaissé n'est jamais conservé automatiquement.</span></div>
      </form>
      <?php else: ?><p class="muted">Aucune transition possible depuis ce statut.</p><?php endif; ?>
      <?php if ($shipBlocked): ?><p class="notice notice-amber small">Expédition bloquée : <?= $order['payment_terms'] === 'deposit' ? 'le solde doit être encaissé avant l\'expédition (Paramètres de paiement).' : 'le paiement intégral doit être encaissé avant l\'expédition.' ?> Reste dû : <?= money($balance) ?>.</p><?php endif; ?>
      <?php if ($order['status'] === 'deposit_paid' && !in_array('preparing', $transitions, true)): ?><p class="notice notice-amber small">Préparation après acompte désactivée (Paramètres de paiement) : encaissez le solde pour préparer la commande.</p><?php endif; ?>
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
    <section class="card" id="paiements">
      <h2>Paiement</h2>
      <p class="small"><strong><?= e($termsLabels[$order['payment_terms']] ?? $order['payment_terms']) ?></strong><?= in_array($order['payment_terms'], ['cod', 'invoice'], true) ? '' : ' · ' . e(payment_method_label($order['payment_method'])) ?><br>
        Client <?= $order['customer_type'] === 'professional' ? 'professionnel' . ($pro ? ' (' . e($pro['company']) . ')' : '') : 'particulier' ?><?= (int) $order['is_wholesale'] === 1 ? ' · <span class="status status-preparing">Commande en gros</span>' : '' ?></p>
      <dl class="totals small">
        <div><dt>Total dû</dt><dd><?= money((int) $order['total']) ?></dd></div>
        <div><dt>dont livraison</dt><dd><?= money((int) $order['shipping_fee']) ?></dd></div>
        <?php if ($order['deposit_amount'] !== null): ?><div><dt>Acompte demandé</dt><dd><?= money((int) $order['deposit_amount']) ?></dd></div><?php endif; ?>
        <div><dt>Encaissé</dt><dd><strong><?= money((int) $order['amount_paid']) ?></strong></dd></div>
        <?php if ((int) $order['amount_refunded'] > 0): ?><div><dt>Remboursé</dt><dd><?= money((int) $order['amount_refunded']) ?></dd></div><?php endif; ?>
        <div class="grand-total"><dt>Reste à payer</dt><dd><?= money($balance) ?></dd></div>
        <?php if ($order['balance_due_at'] && $balance > 0): ?><div><dt>Échéance</dt><dd><?= e(date_fr($order['balance_due_at'], false)) ?><?= $order['balance_due_at'] < gmdate('Y-m-d H:i:s') ? ' <span class="status status-cancelled">dépassée</span>' : '' ?></dd></div><?php endif; ?>
      </dl>
      <p class="small">État : <span class="status status-<?= e($order['payment_status']) ?>"><?= e(payment_status_label($order['payment_status'])) ?></span></p>
      <?php if ($order['payment_declared_at']): ?><p class="notice notice-amber small">Le client indique avoir payé le <?= e(date_fr($order['payment_declared_at'])) ?> (réf. « <?= e((string) $order['payment_declaration']) ?> »). Ce n'est pas une preuve : vérifiez la réception sur le relevé avant d'enregistrer l'encaissement.</p><?php endif; ?>
      <?php if ($order['status'] === 'cancelled' && $refundable > 0): ?><p class="notice notice-amber small">Commande annulée avec <?= money($refundable) ?> encaissé : remboursement à traiter selon les conditions de vente.</p><?php endif; ?>
      <?php if ((int) $order['is_pickup'] === 0 && $balance > 0 && in_array($order['payment_terms'], ['cod', 'deposit'], true) && !$closed): ?><p class="small">À encaisser par le livreur : <strong><?= money($balance) ?></strong> (indiqué sur le récapitulatif imprimable).</p><?php endif; ?>

      <?php if ($transactions): ?>
      <h3>Encaissements et remboursements</h3>
      <ul class="plain-list small">
        <?php foreach ($transactions as $t): ?>
        <li><?= e(date_fr($t['created_at'])) ?> · <strong><?= $t['type'] === 'payment' ? 'Encaissement' : 'Remboursement' ?> <?= money((int) $t['amount']) ?></strong> · <?= e($ledgerMethods[$t['method']] ?? $t['method']) ?>
          <?= $t['reference'] ? ' · réf. <code>' . e($t['reference']) . '</code>' : '' ?><?= $t['reason'] ? ' · ' . e($t['reason']) : '' ?>
          · <span class="muted"><?= $t['first_name'] ? e($t['first_name'] . ' ' . $t['last_name']) : 'prestataire / système' ?></span>
          <?php if ($t['status'] === 'pending'): ?><span class="status status-pending_payment">à effectuer</span>
          <form action="/admin/remboursements/<?= (int) $t['id'] ?>/effectue" method="post" class="inline-form"><?= csrf_field() ?><label class="sr-only" for="rc-<?= (int) $t['id'] ?>">Référence</label><input id="rc-<?= (int) $t['id'] ?>" name="reference" maxlength="120" placeholder="Référence"><button class="btn btn-ghost btn-small" type="submit">Marquer effectué</button></form><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if ($balance > 0 && !$closed): ?>
      <details class="mt"<?= $order['payment_declared_at'] ? ' open' : '' ?>>
        <summary>Enregistrer un encaissement constaté</summary>
        <form action="/admin/commandes/<?= (int) $order['id'] ?>/encaissement" method="post">
          <?= csrf_field() ?>
          <div class="field"><label for="p-amount">Montant reçu (FCFA)</label><input id="p-amount" name="amount" type="number" min="1" max="<?= $balance ?>" required value="<?= \App\Services\PaymentLedger::dueNow($order) ?>"></div>
          <div class="field"><label for="p-method">Moyen</label><select id="p-method" name="method"><?php foreach ($ledgerMethods as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $order['payment_method'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="p-ref">Référence de la transaction</label><input id="p-ref" name="reference" maxlength="120" value="<?= e((string) $order['payment_declaration']) ?>"></div>
          <div class="field"><label for="p-note">Note</label><input id="p-note" name="note" maxlength="255"></div>
          <button class="btn btn-primary btn-small" type="submit">Enregistrer l'encaissement</button>
          <p class="hint">Uniquement après avoir constaté la réception (relevé Mobile Money ou bancaire, espèces remises).</p>
        </form>
      </details>
      <?php endif; ?>
      <?php if ($refundable > 0): ?>
      <details class="mt">
        <summary>Rembourser (<?= money($refundable) ?> remboursable)</summary>
        <form action="/admin/commandes/<?= (int) $order['id'] ?>/remboursement" method="post">
          <?= csrf_field() ?>
          <div class="field"><label for="r-amount">Montant (FCFA)</label><input id="r-amount" name="amount" type="number" min="1" max="<?= $refundable ?>" required value="<?= $refundable ?>"></div>
          <div class="field"><label for="r-reason">Motif</label><input id="r-reason" name="reason" maxlength="255" required></div>
          <div class="field"><label for="r-method">Moyen</label><select id="r-method" name="method"><?php foreach ($ledgerMethods as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $order['payment_method'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="r-ref">Référence</label><input id="r-ref" name="reference" maxlength="120"></div>
          <label class="check"><input type="checkbox" name="completed" value="1" checked> Remboursement déjà effectué (décoché : à effectuer)</label>
          <button class="btn btn-danger btn-small" type="submit">Enregistrer le remboursement</button>
        </form>
      </details>
      <?php endif; ?>

      <h3>Tentatives de paiement en ligne</h3>
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
