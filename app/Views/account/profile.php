<?php use App\Core\View; /** @var array $user @var array $errors @var array $old */ ?>
<div class="page-head"><div class="wrap"><h1>Mon profil</h1></div></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/profil']) ?>
  <form action="/compte/profil" method="post" class="card form-grid" novalidate>
    <?= csrf_field() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field">
      <label for="f-first_name">Prénom</label>
      <input id="f-first_name" name="first_name" required maxlength="100" value="<?= e($old['first_name'] ?? '') ?>"<?= aria_invalid($errors, 'first_name') ?>>
      <?= field_error($errors, 'first_name') ?>
    </div>
    <div class="field">
      <label for="f-last_name">Nom</label>
      <input id="f-last_name" name="last_name" required maxlength="100" value="<?= e($old['last_name'] ?? '') ?>"<?= aria_invalid($errors, 'last_name') ?>>
      <?= field_error($errors, 'last_name') ?>
    </div>
    <div class="field">
      <label for="f-email">E-mail</label>
      <input id="f-email" type="email" value="<?= e($user['email']) ?>" disabled>
      <p class="hint">Pour changer d'adresse e-mail, contactez-nous.</p>
    </div>
    <div class="field">
      <label for="f-phone">Téléphone</label>
      <input id="f-phone" name="phone" type="tel" maxlength="20" value="<?= e($old['phone'] ?? '') ?>"<?= aria_invalid($errors, 'phone') ?>>
      <?= field_error($errors, 'phone') ?>
    </div>
    <label class="check field-full"><input type="checkbox" name="marketing" value="1"<?= !empty($old['marketing_opt_in']) || !empty($old['marketing']) ? ' checked' : '' ?>> Recevoir les nouveautés par e-mail</label>
    <div class="field-full"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
  </form>
</div>
