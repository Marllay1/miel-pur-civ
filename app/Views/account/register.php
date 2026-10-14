<?php use App\Core\View; /** @var array $errors @var array $old */ $val = static fn (string $k) => e($old[$k] ?? ''); ?>
<div class="wrap auth-wrap">
  <div class="card auth-card auth-card-wide">
    <h1>Créer un compte</h1>
    <p class="muted">Un compte permet de suivre vos commandes et d'enregistrer vos adresses. Il n'est pas obligatoire pour commander.</p>
    <?= View::partial('errors-summary', ['errors' => $errors]) ?>
    <form action="/compte/inscription" method="post" class="form-grid" novalidate>
      <?= csrf_field() ?><?= spam_fields() ?>
      <div class="field">
        <label for="f-first_name">Prénom</label>
        <input id="f-first_name" name="first_name" autocomplete="given-name" required maxlength="100" value="<?= $val('first_name') ?>"<?= aria_invalid($errors, 'first_name') ?>>
        <?= field_error($errors, 'first_name') ?>
      </div>
      <div class="field">
        <label for="f-last_name">Nom</label>
        <input id="f-last_name" name="last_name" autocomplete="family-name" required maxlength="100" value="<?= $val('last_name') ?>"<?= aria_invalid($errors, 'last_name') ?>>
        <?= field_error($errors, 'last_name') ?>
      </div>
      <div class="field">
        <label for="f-email">E-mail</label>
        <input id="f-email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= $val('email') ?>"<?= aria_invalid($errors, 'email') ?>>
        <?= field_error($errors, 'email') ?>
      </div>
      <div class="field">
        <label for="f-phone">Téléphone <span class="hint">(facultatif)</span></label>
        <input id="f-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>>
        <?= field_error($errors, 'phone') ?>
      </div>
      <div class="field">
        <label for="f-password">Mot de passe <span class="hint">(10 caractères minimum, lettres et chiffres)</span></label>
        <input id="f-password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="200"<?= aria_invalid($errors, 'password') ?>>
        <?= field_error($errors, 'password') ?>
      </div>
      <div class="field">
        <label for="f-password_confirmation">Confirmation</label>
        <input id="f-password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="200"<?= aria_invalid($errors, 'password_confirmation') ?>>
        <?= field_error($errors, 'password_confirmation') ?>
      </div>
      <label class="check field-full" id="f-consent"><input type="checkbox" name="consent" value="1" required<?= !empty($old['consent']) ? ' checked' : '' ?>> J'ai lu la <a href="/politique-de-confidentialite">politique de confidentialité</a>.</label>
      <?= field_error($errors, 'consent') ?>
      <label class="check field-full"><input type="checkbox" name="marketing" value="1"<?= !empty($old['marketing']) ? ' checked' : '' ?>> Je souhaite recevoir les nouveautés par e-mail (facultatif).</label>
      <div class="field-full"><button class="btn btn-primary" type="submit">Créer mon compte</button></div>
    </form>
    <p class="muted">Déjà inscrit ? <a href="/compte/connexion">Se connecter</a></p>
  </div>
</div>
