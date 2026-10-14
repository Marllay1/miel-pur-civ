<?php
use App\Core\View;
use App\Services\Settings;
/** @var array $errors @var array $old */
$val = static fn (string $k) => e($old[$k] ?? '');
$wa = wa_link('Bonjour, je souhaite un renseignement.');
?>
<div class="page-head"><div class="wrap"><h1>Contact</h1><p class="lead">Une question sur un produit, une commande ou une livraison ? Écrivez-nous.</p></div></div>
<div class="wrap two-cols">
  <form action="/contact" method="post" class="card form-grid" novalidate>
    <?= csrf_field() ?><?= spam_fields() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field">
      <label for="f-name">Nom</label>
      <input id="f-name" name="name" autocomplete="name" maxlength="150" required value="<?= $val('name') ?>"<?= aria_invalid($errors, 'name') ?>>
      <?= field_error($errors, 'name') ?>
    </div>
    <div class="field">
      <label for="f-email">E-mail</label>
      <input id="f-email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= $val('email') ?>"<?= aria_invalid($errors, 'email') ?>>
      <?= field_error($errors, 'email') ?>
    </div>
    <div class="field">
      <label for="f-phone">Téléphone <span class="hint">(facultatif)</span></label>
      <input id="f-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>>
      <?= field_error($errors, 'phone') ?>
    </div>
    <div class="field">
      <label for="f-subject">Objet</label>
      <input id="f-subject" name="subject" maxlength="160" required value="<?= $val('subject') ?>"<?= aria_invalid($errors, 'subject') ?>>
      <?= field_error($errors, 'subject') ?>
    </div>
    <div class="field field-full">
      <label for="f-message">Message</label>
      <textarea id="f-message" name="message" rows="6" maxlength="5000" required<?= aria_invalid($errors, 'message') ?>><?= $val('message') ?></textarea>
      <?= field_error($errors, 'message') ?>
    </div>
    <label class="check field-full" id="f-consent"><input type="checkbox" name="consent" value="1" required<?= !empty($old['consent']) ? ' checked' : '' ?>> J'accepte que mes données soient utilisées pour répondre à ma demande (<a href="/politique-de-confidentialite">confidentialité</a>).</label>
    <?= field_error($errors, 'consent') ?>
    <div class="field-full"><button class="btn btn-primary" type="submit">Envoyer</button></div>
  </form>
  <aside class="card contact-card">
    <h2>Nos coordonnées</h2>
    <ul class="hex-list">
      <li><?= rich_inline(Settings::get('store_address')) ?>, <?= rich_inline(Settings::get('store_city')) ?></li>
      <li>Téléphone : <?= rich_inline(Settings::get('store_phone')) ?></li>
      <li>E-mail : <?= rich_inline(Settings::get('store_email')) ?></li>
      <li>Horaires : <?= rich_inline(Settings::get('opening_hours')) ?></li>
    </ul>
    <?php if ($wa): ?><p><a class="btn btn-ghost" href="<?= e($wa) ?>" rel="noopener" target="_blank">Écrire sur WhatsApp</a></p><?php endif; ?>
  </aside>
</div>
