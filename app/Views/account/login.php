<?php use App\Core\View; /** @var array $errors @var array $old @var string $retour */ ?>
<div class="auth-wrap">
  <div class="auth-card">
    <svg class="auth-logo brand-mark" viewBox="0 0 48 52" aria-hidden="true" focusable="false"><path class="hex" d="M24 1.5 46 14v24L24 50.5 2 38V14z"/><path class="drop" d="M24 12.5c5.2 7.6 8.8 12.6 8.8 17.8a8.8 8.8 0 0 1-17.6 0c0-5.2 3.6-10.2 8.8-17.8z"/></svg>
    <h1><?= e(t('account.login_title')) ?></h1>
    <div class="form-card">
      <?= View::partial('errors-summary', ['errors' => $errors]) ?>
      <form action="/compte/connexion" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="retour" value="<?= e($retour) ?>">
        <div class="field">
          <label for="f-email"><?= e(t('field.email')) ?></label>
          <input id="f-email" name="email" type="email" autocomplete="username" required maxlength="190" value="<?= e($old['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="f-password"><?= e(t('field.password_short')) ?></label>
          <input id="f-password" name="password" type="password" autocomplete="current-password" required maxlength="200">
        </div>
        <button class="btn btn-large btn-block" type="submit"><?= e(t('account.login')) ?></button>
      </form>
      <p class="center mt"><a href="/compte/mot-de-passe-oublie"><?= e(t('account.forgot_link')) ?></a></p>
    </div>
    <div class="group mt">
      <a class="cell" href="/compte/inscription"><span class="cell-icon"><?= icon('person') ?></span><span class="cell-body cell-title"><?= e(t('account.create')) ?></span><span class="cell-chevron"><?= icon('chevron') ?></span></a>
      <a class="cell" href="/panier"><span class="cell-icon gray"><?= icon('bag') ?></span><span class="cell-body cell-title"><?= e(t('account.guest')) ?></span><span class="cell-chevron"><?= icon('chevron') ?></span></a>
    </div>
  </div>
</div>
