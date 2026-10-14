<?php
use App\Core\View;
/** @var array $errors @var array $old @var ?array $order @var array $history */
?>
<div class="page-head"><div class="wrap narrow"><h1>Suivi de commande</h1><p class="lead">Saisissez votre numéro de commande et l'e-mail utilisé lors de l'achat.</p></div></div>
<div class="wrap narrow">
  <form action="/suivi-commande" method="post" class="card form-grid" novalidate>
    <?= csrf_field() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field">
      <label for="f-number">Numéro de commande</label>
      <input id="f-number" name="number" maxlength="40" required placeholder="MPCI-20260101-XXXXXXXX" value="<?= e($old['number'] ?? '') ?>" autocapitalize="characters"<?= aria_invalid($errors, 'number') ?>>
      <?= field_error($errors, 'number') ?>
    </div>
    <div class="field">
      <label for="f-email">E-mail</label>
      <input id="f-email" name="email" type="email" maxlength="190" required value="<?= e($old['email'] ?? '') ?>" autocomplete="email"<?= aria_invalid($errors, 'email') ?>>
      <?= field_error($errors, 'email') ?>
    </div>
    <div class="field-full"><button class="btn btn-primary" type="submit">Rechercher</button></div>
  </form>

  <?php if ($order): ?>
  <section class="card" aria-labelledby="h-track">
    <h2 id="h-track">Commande <?= e($order['number']) ?></h2>
    <p>Statut : <span class="status status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span> · Total : <?= money((int) $order['total']) ?></p>
    <ul class="timeline">
      <?php foreach ($history as $h): ?>
      <li><?= e(date_fr($h['created_at'])) ?> — <strong><?= e(status_label($h['to_status'])) ?></strong></li>
      <?php endforeach; ?>
    </ul>
    <p class="muted small">Pour des raisons de confidentialité, l'adresse et le détail de la commande ne sont visibles que depuis le lien reçu par e-mail ou votre espace client.</p>
  </section>
  <?php endif; ?>
</div>
