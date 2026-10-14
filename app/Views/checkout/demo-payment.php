<?php /** @var array $payment @var array $order @var string $token */ ?>
<div class="wrap narrow demo-pay">
  <div class="demo-pay-banner" role="alert">
    <p class="demo-pay-title">PAIEMENT SIMULÉ</p>
    <p>Cette page imite un prestataire de paiement pour tester le parcours. <strong>Aucune somme n'est débitée</strong> et aucune donnée bancaire n'est demandée.</p>
  </div>
  <div class="card">
    <dl class="totals">
      <div><dt>Commande</dt><dd><?= e($order['number']) ?></dd></div>
      <div><dt>Référence de paiement</dt><dd><?= e($payment['reference']) ?></dd></div>
      <div class="grand-total"><dt>Montant</dt><dd><?= money((int) $payment['amount']) ?></dd></div>
    </dl>
    <?php if ($payment['status'] === 'initiated'): ?>
    <form action="/paiement/demo/<?= e($payment['reference']) ?>" method="post" class="demo-pay-actions">
      <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <button class="btn btn-primary" type="submit" name="outcome" value="succeeded">Simuler un paiement réussi</button>
      <button class="btn btn-ghost" type="submit" name="outcome" value="failed">Simuler un refus</button>
      <button class="link-btn" type="submit" name="outcome" value="cancelled">Annuler</button>
    </form>
    <?php else: ?>
    <p>Ce paiement a déjà été traité (statut : <?= e($payment['status']) ?>).</p>
    <p><a class="btn btn-ghost" href="<?= e(url('/commande/' . $order['number'], ['t' => $token])) ?>">Retour à la commande</a></p>
    <?php endif; ?>
  </div>
</div>
