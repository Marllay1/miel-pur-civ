<?php use App\Core\View; /** @var array $user @var array $errors @var array $old */ ?>
<div class="wrap page-head"><h1><?= e(t('account.profile')) ?></h1></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/profil']) ?>
  <form action="/compte/profil" method="post" class="form-card form-grid" novalidate>
    <?= csrf_field() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field">
      <label for="f-first_name"><?= e(t('field.first_name_short')) ?></label>
      <input id="f-first_name" name="first_name" required maxlength="100" value="<?= e($old['first_name'] ?? '') ?>"<?= aria_invalid($errors, 'first_name') ?>>
      <?= field_error($errors, 'first_name') ?>
    </div>
    <div class="field">
      <label for="f-last_name"><?= e(t('field.last_name_short')) ?></label>
      <input id="f-last_name" name="last_name" required maxlength="100" value="<?= e($old['last_name'] ?? '') ?>"<?= aria_invalid($errors, 'last_name') ?>>
      <?= field_error($errors, 'last_name') ?>
    </div>
    <div class="field">
      <label for="f-email"><?= e(t('field.email')) ?></label>
      <input id="f-email" type="email" value="<?= e($user['email']) ?>" disabled>
      <span class="hint"><?= e(t('account.email_locked')) ?></span>
    </div>
    <div class="field">
      <label for="f-phone"><?= e(t('field.phone_short')) ?></label>
      <input id="f-phone" name="phone" type="tel" maxlength="20" value="<?= e($old['phone'] ?? '') ?>"<?= aria_invalid($errors, 'phone') ?>>
      <?= field_error($errors, 'phone') ?>
    </div>
    <label class="switch field-full"><span><?= e(t('account.marketing_short')) ?></span><input type="checkbox" name="marketing" value="1"<?= !empty($old['marketing_opt_in']) || !empty($old['marketing']) ? ' checked' : '' ?>></label>
    <div class="field-full mt"><button class="btn" type="submit"><?= e(t('form.save')) ?></button></div>
  </form>
</div>
