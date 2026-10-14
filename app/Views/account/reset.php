<?php /** @var array $errors @var bool $valid @var string $token */ ?>
<div class="auth-wrap">
  <div class="auth-card">
    <h1><?= e(t('account.reset_title')) ?></h1>
    <div class="form-card">
      <?php if (!$valid): ?>
      <p class="notice notice-amber"><?= icon('info') ?> <?= e(t('account.reset_invalid')) ?></p>
      <a class="btn btn-gray btn-block" href="/compte/mot-de-passe-oublie"><?= e(t('account.reset_again')) ?></a>
      <?php else: ?>
      <form action="/compte/reinitialiser/<?= e($token) ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="f-password"><?= e(t('account.new_password')) ?> <span class="hint"><?= e(t('password.hint')) ?></span></label>
          <input id="f-password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="200"<?= aria_invalid($errors, 'password') ?>>
          <?= field_error($errors, 'password') ?>
        </div>
        <div class="field">
          <label for="f-password_confirmation"><?= e(t('field.password_confirmation_short')) ?></label>
          <input id="f-password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="200"<?= aria_invalid($errors, 'password_confirmation') ?>>
          <?= field_error($errors, 'password_confirmation') ?>
        </div>
        <button class="btn btn-large btn-block" type="submit"><?= e(t('form.save')) ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
