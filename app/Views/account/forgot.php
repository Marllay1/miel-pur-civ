<?php /** @var array $errors @var bool $sent */ ?>
<div class="auth-wrap">
  <div class="auth-card">
    <h1><?= e(t('account.forgot_title')) ?></h1>
    <div class="form-card">
      <?php if ($sent): ?>
      <p class="notice notice-green" role="status"><?= icon('mail') ?> <?= e(t('account.reset_sent')) ?></p>
      <a class="btn btn-gray btn-block" href="/compte/connexion"><?= e(t('account.back_login')) ?></a>
      <?php else: ?>
      <p class="muted"><?= e(t('account.forgot_lead')) ?></p>
      <form action="/compte/mot-de-passe-oublie" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="f-email"><?= e(t('field.email')) ?></label>
          <input id="f-email" name="email" type="email" autocomplete="email" required maxlength="190"<?= aria_invalid($errors, 'email') ?>>
          <?= field_error($errors, 'email') ?>
        </div>
        <button class="btn btn-large btn-block" type="submit"><?= e(t('account.send_link')) ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
