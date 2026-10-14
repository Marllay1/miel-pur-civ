<?php use App\Core\View; /** @var array $errors */ ?>
<div class="page-head"><div class="wrap"><h1>Mot de passe</h1></div></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/mot-de-passe']) ?>
  <form action="/compte/mot-de-passe" method="post" class="card" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="f-current_password">Mot de passe actuel</label>
      <input id="f-current_password" name="current_password" type="password" autocomplete="current-password" required<?= aria_invalid($errors, 'current_password') ?>>
      <?= field_error($errors, 'current_password') ?>
    </div>
    <div class="field">
      <label for="f-password">Nouveau mot de passe <span class="hint">(10 caractères minimum, lettres et chiffres)</span></label>
      <input id="f-password" name="password" type="password" autocomplete="new-password" required minlength="10"<?= aria_invalid($errors, 'password') ?>>
      <?= field_error($errors, 'password') ?>
    </div>
    <div class="field">
      <label for="f-password_confirmation">Confirmation</label>
      <input id="f-password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required<?= aria_invalid($errors, 'password_confirmation') ?>>
      <?= field_error($errors, 'password_confirmation') ?>
    </div>
    <button class="btn btn-primary" type="submit">Modifier</button>
  </form>
</div>
