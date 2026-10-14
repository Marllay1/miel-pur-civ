<?php use App\Core\View; /** @var array $errors @var array $old @var string $retour */ ?>
<div class="wrap auth-wrap">
  <div class="card auth-card">
    <h1>Connexion</h1>
    <?= View::partial('errors-summary', ['errors' => $errors]) ?>
    <form action="/compte/connexion" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="retour" value="<?= e($retour) ?>">
      <div class="field">
        <label for="f-email">E-mail</label>
        <input id="f-email" name="email" type="email" autocomplete="username" required maxlength="190" value="<?= e($old['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="f-password">Mot de passe</label>
        <input id="f-password" name="password" type="password" autocomplete="current-password" required maxlength="200">
      </div>
      <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
    </form>
    <p><a href="/compte/mot-de-passe-oublie">Mot de passe oublié ?</a></p>
    <p class="muted">Pas encore de compte ? <a href="/compte/inscription">Créer un compte</a> — ou <a href="/panier">commandez sans compte</a>.</p>
  </div>
</div>
