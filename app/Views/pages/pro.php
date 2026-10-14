<?php
use App\Core\View;
/** @var array $errors @var array $old @var array $activities */
$val = static fn (string $k) => e($old[$k] ?? '');
?>
<div class="page-head">
  <div class="wrap">
    <p class="eyebrow">Professionnels</p>
    <h1>Commandes professionnelles</h1>
    <p class="lead">Hôtels, restaurants, pâtisseries, épiceries, cosmétique artisanale, cadeaux d'entreprise : décrivez votre besoin, nous vous adressons un devis.</p>
  </div>
</div>
<div class="wrap two-cols">
  <form action="/commandes-professionnelles" method="post" class="card form-grid" novalidate>
    <?= csrf_field() ?><?= spam_fields() ?>
    <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
    <div class="field">
      <label for="f-company">Établissement</label>
      <input id="f-company" name="company" autocomplete="organization" maxlength="160" required value="<?= $val('company') ?>"<?= aria_invalid($errors, 'company') ?>>
      <?= field_error($errors, 'company') ?>
    </div>
    <div class="field">
      <label for="f-activity">Type d'activité</label>
      <select id="f-activity" name="activity" required<?= aria_invalid($errors, 'activity') ?>>
        <option value="">Choisir…</option>
        <?php foreach ($activities as $k => $label): ?><option value="<?= e($k) ?>"<?= ($old['activity'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <?= field_error($errors, 'activity') ?>
    </div>
    <div class="field">
      <label for="f-contact_name">Nom du contact</label>
      <input id="f-contact_name" name="contact_name" autocomplete="name" maxlength="150" required value="<?= $val('contact_name') ?>"<?= aria_invalid($errors, 'contact_name') ?>>
      <?= field_error($errors, 'contact_name') ?>
    </div>
    <div class="field">
      <label for="f-phone">Téléphone</label>
      <input id="f-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" required value="<?= $val('phone') ?>"<?= aria_invalid($errors, 'phone') ?>>
      <?= field_error($errors, 'phone') ?>
    </div>
    <div class="field">
      <label for="f-email">E-mail</label>
      <input id="f-email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= $val('email') ?>"<?= aria_invalid($errors, 'email') ?>>
      <?= field_error($errors, 'email') ?>
    </div>
    <div class="field">
      <label for="f-city">Ville</label>
      <input id="f-city" name="city" maxlength="100" value="<?= $val('city') ?>">
    </div>
    <div class="field">
      <label for="f-products_interest">Produits recherchés</label>
      <input id="f-products_interest" name="products_interest" maxlength="255" placeholder="Ex. : miel liquide en seau, cire" value="<?= $val('products_interest') ?>">
    </div>
    <div class="field">
      <label for="f-estimated_volume">Volume estimé</label>
      <input id="f-estimated_volume" name="estimated_volume" maxlength="120" placeholder="Ex. : 50 kg par mois" value="<?= $val('estimated_volume') ?>">
    </div>
    <div class="field">
      <label for="f-frequency">Fréquence</label>
      <select id="f-frequency" name="frequency"<?= aria_invalid($errors, 'frequency') ?>>
        <option value="">—</option>
        <?php foreach (['ponctuelle' => 'Ponctuelle', 'mensuelle' => 'Mensuelle', 'hebdomadaire' => 'Hebdomadaire', 'autre' => 'Autre'] as $k => $l): ?><option value="<?= $k ?>"<?= ($old['frequency'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field field-full">
      <label for="f-message">Précisions</label>
      <textarea id="f-message" name="message" rows="5" maxlength="5000"><?= $val('message') ?></textarea>
    </div>
    <label class="check field-full" id="f-consent"><input type="checkbox" name="consent" value="1" required<?= !empty($old['consent']) ? ' checked' : '' ?>> J'accepte que ces informations soient utilisées pour établir un devis (<a href="/politique-de-confidentialite">confidentialité</a>).</label>
    <?= field_error($errors, 'consent') ?>
    <div class="field-full"><button class="btn btn-primary" type="submit">Envoyer la demande de devis</button></div>
  </form>
  <aside class="card">
    <h2>Ce que nous proposons</h2>
    <ul class="hex-list">
      <li>Conditionnements adaptés aux volumes (seaux, bidons) [À CONFIRMER]</li>
      <li>Tarifs dégressifs selon les quantités [À CONFIRMER]</li>
      <li>Livraisons régulières selon un calendrier convenu [À CONFIRMER]</li>
      <li>Fiches produits et documents sur demande [À CONFIRMER]</li>
    </ul>
    <p class="muted small">Les éléments marqués sont à valider par l'entreprise avant mise en ligne.</p>
  </aside>
</div>
