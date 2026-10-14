<?php /** @var array $errors @var bool $sent */ ?>
<div class="wrap auth-wrap">
  <div class="card auth-card">
    <h1>Mot de passe oublié</h1>
    <?php if ($sent): ?>
    <p class="flash flash-success" role="status">Si un compte correspond à cette adresse, un lien de réinitialisation (valable 1 heure) vient d'être envoyé.</p>
    <p><a href="/compte/connexion">Retour à la connexion</a></p>
    <?php else: ?>
    <p class="muted">Indiquez l'e-mail de votre compte : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>
    <form action="/compte/mot-de-passe-oublie" method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="f-email">E-mail</label>
        <input id="f-email" name="email" type="email" autocomplete="email" required maxlength="190"<?= aria_invalid($errors, 'email') ?>>
        <?= field_error($errors, 'email') ?>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Envoyer le lien</button>
    </form>
    <?php endif; ?>
  </div>
</div>
