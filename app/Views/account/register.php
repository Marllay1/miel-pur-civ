<?php use App\Core\View; /** @var array $errors @var array $old */ $val = static fn (string $k) => e($old[$k] ?? ''); ?>
<div class="auth-wrap">
  <div class="auth-card auth-card-wide">
    <h1><?= e(t('account.register_title')) ?></h1>
    <p class="muted center"><?= e(t('account.register_lead')) ?></p>
    <div class="form-card">
      <?= View::partial('errors-summary', ['errors' => $errors]) ?>
      <form action="/compte/inscription" method="post" class="form-grid" novalidate>
        <?= csrf_field() ?><?= spam_fields() ?>
        <div class="field">
          <label for="f-first_name"><?= e(t('field.first_name_short')) ?></label>
          <input id="f-first_name" name="first_name" autocomplete="given-name" required maxlength="100" value="<?= $val('first_name') ?>"<?= aria_invalid($errors, 'first_name') ?>>
          <?= field_error($errors, 'first_name') ?>
        </div>
        <div class="field">
          <label for="f-last_name"><?= e(t('field.last_name_short')) ?></label>
          <input id="f-last_name" name="last_name" autocomplete="family-name" required maxlength="100" value="<?= $val('last_name') ?>"<?= aria_invalid($errors, 'last_name') ?>>
          <?= field_error($errors, 'last_name') ?>
        </div>
        <div class="field">
          <label for="f-email"><?= e(t('field.email')) ?></label>
          <input id="f-email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= $val('email') ?>"<?= aria_invalid($errors, 'email') ?>>
          <?= field_error($errors, 'email') ?>
        </div>
        <div class="field">
          <label for="f-phone"><?= e(t('field.phone_short')) ?> <span class="hint"><?= e(t('form.optional')) ?></span></label>
          <input id="f-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>>
          <?= field_error($errors, 'phone') ?>
        </div>
        <div class="field">
          <label for="f-password"><?= e(t('field.password_short')) ?> <span class="hint"><?= e(t('password.hint')) ?></span></label>
          <input id="f-password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="200"<?= aria_invalid($errors, 'password') ?>>
          <?= field_error($errors, 'password') ?>
        </div>
        <div class="field">
          <label for="f-password_confirmation"><?= e(t('field.password_confirmation_short')) ?></label>
          <input id="f-password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="200"<?= aria_invalid($errors, 'password_confirmation') ?>>
          <?= field_error($errors, 'password_confirmation') ?>
        </div>
        <label class="check field-full" id="f-consent"><input type="checkbox" name="consent" value="1" required<?= !empty($old['consent']) ? ' checked' : '' ?>> <span><?= e(t('account.privacy_read')) ?> <a href="/politique-de-confidentialite"><?= e(t('footer.privacy')) ?></a></span></label>
        <div class="field-full"><?= field_error($errors, 'consent') ?></div>
        <label class="check field-full"><input type="checkbox" name="marketing" value="1"<?= !empty($old['marketing']) ? ' checked' : '' ?>> <span><?= e(t('account.marketing')) ?></span></label>
        <div class="field-full"><button class="btn btn-large btn-block" type="submit"><?= e(t('account.create')) ?></button></div>
      </form>
    </div>
    <p class="center mt"><?= e(t('account.already')) ?> <a href="/compte/connexion"><?= e(t('account.login')) ?></a></p>
  </div>
</div>
