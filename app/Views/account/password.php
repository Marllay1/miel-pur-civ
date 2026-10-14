<?php use App\Core\View; /** @var array $errors */ ?>
<div class="wrap page-head"><h1><?= e(t('account.password')) ?></h1></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/mot-de-passe']) ?>
  <form action="/compte/mot-de-passe" method="post" class="form-card" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="f-current_password"><?= e(t('account.current_password')) ?></label>
      <input id="f-current_password" name="current_password" type="password" autocomplete="current-password" required<?= aria_invalid($errors, 'current_password') ?>>
      <?= field_error($errors, 'current_password') ?>
    </div>
    <div class="field">
      <label for="f-password"><?= e(t('account.new_password')) ?> <span class="hint"><?= e(t('password.hint')) ?></span></label>
      <input id="f-password" name="password" type="password" autocomplete="new-password" required minlength="10"<?= aria_invalid($errors, 'password') ?>>
      <?= field_error($errors, 'password') ?>
    </div>
    <div class="field">
      <label for="f-password_confirmation"><?= e(t('field.password_confirmation_short')) ?></label>
      <input id="f-password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required<?= aria_invalid($errors, 'password_confirmation') ?>>
      <?= field_error($errors, 'password_confirmation') ?>
    </div>
    <p class="hint"><?= e(t('account.password_sessions')) ?></p>
    <button class="btn" type="submit"><?= e(t('account.change_password')) ?></button>
  </form>
</div>
