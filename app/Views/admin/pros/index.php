<?php
use App\Core\View;
/** @var array $pros @var array $errors @var array $old */
?>
<h1>Clients professionnels</h1>
<p class="muted">Être professionnel ne suffit pas pour payer en différé : le <strong>paiement sur facture</strong> n'est proposé qu'aux comptes autorisés ici, dans la limite de leur plafond et avec leur délai de paiement. Le moyen « Paiement sur facture » doit aussi être activé dans <a href="/admin/paiements/invoice">Moyens de paiement</a>.</p>
<?php if (!$pros): ?><p class="card muted">Aucun compte professionnel.</p><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Entreprise</th><th>Contact</th><th>Paiement sur facture</th><th class="num">Plafond</th><th class="num">Délai</th><th class="num">En cours dû</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($pros as $p): ?>
  <tr>
    <td><strong><?= e($p['company']) ?></strong><?= (int) $p['is_active'] ? '' : ' <span class="status status-cancelled">inactif</span>' ?></td>
    <td class="small"><?= e($p['first_name'] . ' ' . $p['last_name']) ?><br><?= e($p['email']) ?></td>
    <td><?= (int) $p['invoice_allowed'] ? '<span class="status status-delivered">Autorisé</span>' : '<span class="status status-cancelled">Non autorisé</span>' ?></td>
    <td class="num"><?= $p['credit_limit'] === null ? '<span class="muted">non fixé</span>' : money((int) $p['credit_limit']) ?></td>
    <td class="num"><?= (int) $p['payment_days'] ?> j</td>
    <td class="num"><?= money((int) $p['outstanding']) ?></td>
    <td><a class="btn btn-ghost btn-small" href="/admin/professionnels/<?= (int) $p['id'] ?>">Modifier</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<section class="card">
  <h2>Ajouter un client professionnel</h2>
  <p class="small muted">Le client doit avoir créé son compte sur le site. Les conditions (plafond, délai) sont celles convenues avec lui par l'entreprise : aucune valeur n'est proposée d'office, hormis le délai par défaut des Paramètres de paiement.</p>
  <form action="/admin/professionnels" method="post" class="form-grid" novalidate>
    <?= csrf_field() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field"><label for="p-email">E-mail du compte client</label><input id="p-email" name="email" type="email" required maxlength="190" value="<?= e($old['email'] ?? '') ?>"<?= aria_invalid($errors, 'email') ?>><?= field_error($errors, 'email') ?></div>
    <div class="field"><label for="p-company">Entreprise</label><input id="p-company" name="company" required maxlength="160" value="<?= e($old['company'] ?? '') ?>"<?= aria_invalid($errors, 'company') ?>><?= field_error($errors, 'company') ?></div>
    <?= View::partial('pro-terms', ['old' => $old + ['is_active' => '1'], 'errors' => $errors]) ?>
    <div class="field-full"><button class="btn btn-primary" type="submit">Créer le compte professionnel</button></div>
  </form>
</section>
