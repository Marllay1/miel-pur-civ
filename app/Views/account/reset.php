<?php /** @var array $errors @var bool $valid @var string $token */ ?>
<div class="wrap auth-wrap">
  <div class="card auth-card">
    <h1>Nouveau mot de passe</h1>
    <?php if (!$valid): ?>
    <p class="flash flash-error">Ce lien est invalide, déjà utilisé ou expiré.</p>
    <p><a href="/compte/mot-de-passe-oublie">Demander un nouveau lien</a></p>
    <?php else: ?>
    <form action="/compte/reinitialiser/<?= e($token) ?>" method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="f-password">Nouveau mot de passe <span class="hint">(10 caractères minimum, lettres et chiffres)</span></label>
        <input id="f-password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="200"<?= aria_invalid($errors, 'password') ?>>
        <?= field_error($errors, 'password') ?>
      </div>
      <div class="field">
        <label for="f-password_confirmation">Confirmation</label>
        <input id="f-password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="200"<?= aria_invalid($errors, 'password_confirmation') ?>>
        <?= field_error($errors, 'password_confirmation') ?>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Enregistrer</button>
    </form>
    <?php endif; ?>
  </div>
</div>
