<?php
use App\Core\View;
/** @var array $pro @var array $old @var array $errors @var array $orders @var int $outstanding */
?>
<p><a href="/admin/professionnels">← Clients professionnels</a></p>
<h1><?= e($pro['company']) ?></h1>
<p class="muted"><?= e($pro['first_name'] . ' ' . $pro['last_name']) ?> · <a href="mailto:<?= e($pro['email']) ?>"><?= e($pro['email']) ?></a><?= $pro['phone'] ? ' · ' . e($pro['phone']) : '' ?> · <a href="/admin/clients/<?= (int) $pro['user_id'] ?>">Compte client</a></p>
<p class="small">En cours dû sur facture : <strong><?= money($outstanding) ?></strong><?php if ($pro['credit_limit'] !== null): ?> · disponible : <strong><?= money(max(0, (int) $pro['credit_limit'] - $outstanding)) ?></strong><?php endif; ?>
  <?php if ($pro['approved_at']): ?> · autorisé le <?= e(date_fr($pro['approved_at'], false)) ?><?php endif; ?></p>
<form action="/admin/professionnels/<?= (int) $pro['id'] ?>" method="post" class="card form-grid" novalidate>
  <?= csrf_field() ?>
  <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
  <div class="field"><label for="p-company">Entreprise</label><input id="p-company" name="company" required maxlength="160" value="<?= e((string) $old['company']) ?>"<?= aria_invalid($errors, 'company') ?>><?= field_error($errors, 'company') ?></div>
  <?= View::partial('pro-terms', ['old' => $old, 'errors' => $errors]) ?>
  <div class="field-full"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
</form>
<section class="card">
  <h2>Commandes</h2>
  <?php if (!$orders): ?><p class="muted">Aucune commande.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Commande</th><th>Statut</th><th>Paiement</th><th class="num">Total</th><th class="num">Encaissé</th><th>Échéance</th></tr></thead>
    <tbody><?php foreach ($orders as $o): ?>
      <tr><td><a href="/admin/commandes/<?= (int) $o['id'] ?>"><?= e($o['number']) ?></a><br><span class="small muted"><?= e(date_fr($o['created_at'], false)) ?></span></td>
        <td><?= e(status_label($o['status'])) ?></td><td class="small"><?= e(payment_status_label($o['payment_status'])) ?></td>
        <td class="num"><?= money((int) $o['total']) ?></td><td class="num"><?= money((int) $o['amount_paid']) ?></td>
        <td class="small"><?= $o['balance_due_at'] ? e(date_fr($o['balance_due_at'], false)) : '—' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
